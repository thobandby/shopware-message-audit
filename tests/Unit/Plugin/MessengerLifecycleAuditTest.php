<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Tests\Unit\Plugin;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use MessengerHistoryDashboard\Core\Message\SampleFailureMessage;
use MessengerHistoryDashboard\Core\Message\SampleSuccessMessage;
use MessengerHistoryDashboard\Core\Messenger\Middleware\DispatchAuditMiddleware;
use MessengerHistoryDashboard\Core\Messenger\Subscriber\MessengerWorkerSubscriber;
use MessengerHistoryDashboard\Core\Operator\OperatorActionPolicy;
use MessengerHistoryDashboard\Core\Repository\FailureRepository;
use MessengerHistoryDashboard\Core\Repository\MessageMetadata;
use MessengerHistoryDashboard\Core\Repository\MessageRepository;
use MessengerHistoryDashboard\Core\Repository\StateChangeContextRepository;
use MessengerHistoryDashboard\Core\Repository\TransitionRepository;
use MessengerHistoryDashboard\Core\Subscriber\StateChangeAuditSubscriber;
use MessengerHistoryDashboard\Core\Service\MessageAuditWriter;
use MessengerHistoryDashboard\Core\Service\PayloadSerializer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

final class MessengerLifecycleAuditTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->connection->executeStatement(
            'CREATE TABLE mh_message (
                id VARCHAR(64) NOT NULL PRIMARY KEY,
                message_class VARCHAR(255) NOT NULL,
                source VARCHAR(32) NOT NULL DEFAULT "messenger",
                subject_type VARCHAR(64) DEFAULT NULL,
                subject_id VARCHAR(64) DEFAULT NULL,
                correlation_id VARCHAR(64) DEFAULT NULL,
                causation_id VARCHAR(64) DEFAULT NULL,
                transport_name VARCHAR(255) DEFAULT NULL,
                business_reference VARCHAR(255) DEFAULT NULL,
                payload_json TEXT DEFAULT NULL,
                status VARCHAR(32) NOT NULL,
                retry_count INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )'
        );
        $this->connection->executeStatement(
            'CREATE TABLE mh_transition (
                id VARCHAR(64) NOT NULL PRIMARY KEY,
                message_id VARCHAR(64) NOT NULL,
                event VARCHAR(64) NOT NULL,
                created_at DATETIME NOT NULL
            )'
        );
        $this->connection->executeStatement(
            'CREATE TABLE mh_failure (
                id VARCHAR(64) NOT NULL PRIMARY KEY,
                message_id VARCHAR(64) NOT NULL,
                exception_class VARCHAR(255) NOT NULL,
                error_message TEXT NOT NULL,
                created_at DATETIME NOT NULL
            )'
        );
    }

    public function testDispatchAndHandledEventUpdateStoredStatus(): void
    {
        $writer = $this->createWriter();
        $bus = new MessageBus([new DispatchAuditMiddleware($writer)]);
        $subscriber = new MessengerWorkerSubscriber($writer);

        $dispatchedEnvelope = $bus->dispatch(new SampleSuccessMessage('demo-success-1', 'Demo success'));
        $messageId = $this->extractMessageId($dispatchedEnvelope);

        $dispatched = $this->findMessage($messageId);
        self::assertSame('dispatched', $dispatched['status']);
        self::assertSame('demo-success-1', $dispatched['business_reference']);

        $subscriber->onReceived(new WorkerMessageReceivedEvent($dispatchedEnvelope, 'async'));
        $received = $this->findMessage($messageId);
        self::assertSame('received', $received['status']);

        $handledEnvelope = $dispatchedEnvelope->with(new ReceivedStamp('async'));
        $subscriber->onHandled(new WorkerMessageHandledEvent($handledEnvelope, 'async'));

        $handled = $this->findMessage($messageId);
        self::assertSame('handled', $handled['status']);
        self::assertSame('async', $handled['transport_name']);

        $transitions = $this->connection->fetchFirstColumn(
            'SELECT event FROM mh_transition WHERE message_id = :id ORDER BY created_at ASC',
            ['id' => $messageId]
        );

        self::assertContains('dispatched', $transitions);
        self::assertContains('received', $transitions);
        self::assertContains('handled', $transitions);
    }

    public function testFailedEventStoresFailureDetails(): void
    {
        $writer = $this->createWriter();
        $bus = new MessageBus([new DispatchAuditMiddleware($writer)]);
        $subscriber = new MessengerWorkerSubscriber($writer);

        $dispatchedEnvelope = $bus->dispatch(new SampleFailureMessage('demo-failure-1', 'Demo failure'));
        $messageId = $this->extractMessageId($dispatchedEnvelope);

        $failedEnvelope = $dispatchedEnvelope->with(new ReceivedStamp('async'));
        $exception = new \RuntimeException('Simulated worker failure');

        $subscriber->onFailed(new WorkerMessageFailedEvent($failedEnvelope, 'async', $exception));

        $failed = $this->findMessage($messageId);
        self::assertSame('failed', $failed['status']);
        self::assertSame('async', $failed['transport_name']);

        $failure = $this->connection->fetchAssociative(
            'SELECT exception_class, error_message FROM mh_failure WHERE message_id = :id',
            ['id' => $messageId]
        );

        self::assertNotFalse($failure);
        self::assertSame(\RuntimeException::class, $failure['exception_class']);
        self::assertSame('Simulated worker failure', $failure['error_message']);
    }

    public function testStateChangeEntryCanBeRecordedWithoutMessengerWorker(): void
    {
        $writer = $this->createWriter();
        $entryId = hash('sha256', 'order:123:complete');

        $writer->recordStateChange(
            $entryId,
            'Shopware\\Checkout\\Order\\StatusTransition',
            'completed',
            [
                'subjectType' => 'order',
                'subjectId' => '123',
                'previousState' => 'in_progress',
                'nextState' => 'completed',
                'businessReference' => '10001',
            ],
            new MessageMetadata(
                correlationId: 'order-123',
                businessReference: '10001',
                source: 'state_change',
                subjectType: 'order',
                subjectId: '123'
            ),
            'state_enter.order.state.completed'
        );

        $message = $this->connection->fetchAssociative(
            'SELECT id, source, subject_type, subject_id, status, business_reference FROM mh_message WHERE id = :id',
            ['id' => $entryId]
        );

        self::assertNotFalse($message);
        self::assertSame('state_change', $message['source']);
        self::assertSame('order', $message['subject_type']);
        self::assertSame('123', $message['subject_id']);
        self::assertSame('completed', $message['status']);
        self::assertSame('10001', $message['business_reference']);

        $transition = $this->connection->fetchOne(
            'SELECT event FROM mh_transition WHERE message_id = :id',
            ['id' => $entryId]
        );

        self::assertSame('state_enter.order.state.completed', $transition);
    }

    public function testStateChangeSubscriberRecordsShopwareStateEvent(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE `order` (
                id BLOB NOT NULL PRIMARY KEY,
                order_number VARCHAR(255) NOT NULL
            )'
        );
        $this->connection->executeStatement(
            'INSERT INTO `order` (id, order_number) VALUES (X\'00112233445566778899AABBCCDDEEFF\', \'10001\')'
        );

        $writer = $this->createWriter();
        $subscriber = new StateChangeAuditSubscriber($writer, new StateChangeContextRepository($this->connection), new NullLogger());
        $subscriber->onStateChange(
            new StateMachineStateChangeEvent(
                Context::createDefaultContext(),
                StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
                new Transition('order', '00112233445566778899aabbccddeeff', 'complete', 'stateId'),
                $this->createStateMachine(OrderStates::STATE_MACHINE),
                $this->createState(OrderStates::STATE_IN_PROGRESS),
                $this->createState(OrderStates::STATE_COMPLETED)
            )
        );

        $message = $this->connection->fetchAssociative(
            'SELECT source, subject_type, subject_id, correlation_id, business_reference, status, payload_json
             FROM mh_message
             WHERE source = :source
             ORDER BY created_at DESC
             LIMIT 1',
            ['source' => 'state_change']
        );

        self::assertNotFalse($message);
        self::assertSame('order', $message['subject_type']);
        self::assertSame('00112233445566778899aabbccddeeff', $message['subject_id']);
        self::assertSame('00112233445566778899aabbccddeeff', $message['correlation_id']);
        self::assertSame('10001', $message['business_reference']);
        self::assertSame(OrderStates::STATE_COMPLETED, $message['status']);

        $payload = json_decode((string) $message['payload_json'], true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame('state_enter.order.state.completed', $payload['eventName']);
        self::assertSame(OrderStates::STATE_IN_PROGRESS, $payload['previousState']);
        self::assertSame(OrderStates::STATE_COMPLETED, $payload['nextState']);
        self::assertSame('complete', $payload['transitionAction']);

        $transition = $this->connection->fetchOne(
            'SELECT event FROM mh_transition WHERE message_id = :id',
            ['id' => $this->assertAndReturnMessageId($message)]
        );

        self::assertSame('state_enter.order.state.completed', $transition);
    }

    public function testStateChangeSubscriberIgnoresLeaveEvents(): void
    {
        $writer = $this->createWriter();
        $subscriber = new StateChangeAuditSubscriber($writer, new StateChangeContextRepository($this->connection), new NullLogger());

        $subscriber->onStateChange(
            new StateMachineStateChangeEvent(
                Context::createDefaultContext(),
                StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_LEAVE,
                new Transition('order', '00112233445566778899aabbccddeeff', 'process', 'stateId'),
                $this->createStateMachine(OrderStates::STATE_MACHINE),
                $this->createState(OrderStates::STATE_OPEN),
                $this->createState(OrderStates::STATE_IN_PROGRESS)
            )
        );

        self::assertSame(
            0,
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mh_message WHERE source = :source', ['source' => 'state_change'])
        );
    }

    public function testStateChangeSubscriberIgnoresUntrackedStates(): void
    {
        $writer = $this->createWriter();
        $subscriber = new StateChangeAuditSubscriber($writer, new StateChangeContextRepository($this->connection), new NullLogger());

        $subscriber->onStateChange(
            new StateMachineStateChangeEvent(
                Context::createDefaultContext(),
                StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
                new Transition('order', '00112233445566778899aabbccddeeff', 'archive', 'stateId'),
                $this->createStateMachine(OrderStates::STATE_MACHINE),
                $this->createState(OrderStates::STATE_OPEN),
                $this->createState('archived')
            )
        );

        self::assertSame(
            0,
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mh_message WHERE source = :source', ['source' => 'state_change'])
        );
    }

    private function createWriter(): MessageAuditWriter
    {
        return new MessageAuditWriter(
            new MessageRepository($this->connection, new OperatorActionPolicy()),
            new TransitionRepository($this->connection),
            new FailureRepository($this->connection),
            new PayloadSerializer()
        );
    }

    /**
     * @return array<string, scalar|null>
     */
    private function findMessage(string $messageId): array
    {
        $message = $this->connection->fetchAssociative(
            'SELECT id, source, status, transport_name, business_reference FROM mh_message WHERE id = :id',
            ['id' => $messageId]
        );

        self::assertNotFalse($message);

        return $message;
    }

    private function extractMessageId(Envelope $envelope): string
    {
        $message = $this->connection->fetchOne(
            'SELECT id FROM mh_message WHERE business_reference = :reference ORDER BY created_at DESC LIMIT 1',
            ['reference' => $envelope->getMessage()->reference]
        );

        self::assertIsString($message);

        return $message;
    }

    /**
     * @param array<string, scalar|null> $message
     */
    private function assertAndReturnMessageId(array $message): string
    {
        $messageId = $this->connection->fetchOne(
            'SELECT id FROM mh_message
             WHERE source = :source AND subject_id = :subjectId
             ORDER BY created_at DESC
             LIMIT 1',
            [
                'source' => $message['source'],
                'subjectId' => $message['subject_id'],
            ]
        );

        self::assertIsString($messageId);

        return $messageId;
    }

    private function createStateMachine(string $technicalName): StateMachineEntity
    {
        $stateMachine = new StateMachineEntity();
        $stateMachine->setTechnicalName($technicalName);

        return $stateMachine;
    }

    private function createState(string $technicalName): StateMachineStateEntity
    {
        $state = new StateMachineStateEntity();
        $state->setTechnicalName($technicalName);

        return $state;
    }
}
