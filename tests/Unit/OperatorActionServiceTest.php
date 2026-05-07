<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Tests\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Thorsten\MessengerHistory\Messenger\Contract\CurrentOperatorProviderInterface;
use Thorsten\MessengerHistory\Messenger\Contract\MessageAuditRepositoryInterface;
use Thorsten\MessengerHistory\Messenger\Contract\NotificationDispatcherInterface;
use Thorsten\MessengerHistory\Messenger\Contract\OperatorActionExecutorInterface;
use Thorsten\MessengerHistory\Messenger\Contract\OperatorActionRepositoryInterface;
use Thorsten\MessengerHistory\Messenger\Contract\OperatorPolicyInterface;
use Thorsten\MessengerHistory\Messenger\Contract\PolicyDecisionInterface;
use Thorsten\MessengerHistory\Messenger\Model\MessageSnapshot;
use Thorsten\MessengerHistory\Messenger\Model\NotificationEvent;
use Thorsten\MessengerHistory\Messenger\Model\OperatorActionRecord;
use Thorsten\MessengerHistory\Messenger\Model\OperatorActionResult;
use Thorsten\MessengerHistory\Messenger\Model\OperatorIdentity;
use Thorsten\MessengerHistory\Messenger\Operator\OperatorActionService;

final class OperatorActionServiceTest extends TestCase
{
    private OperatorActionService $operatorActionService;
    private MessageAuditRepositoryInterface|MockObject $auditRepository;
    private OperatorActionRepositoryInterface|MockObject $operatorActionRepository;
    private OperatorActionExecutorInterface|MockObject $executor;
    private OperatorPolicyInterface|MockObject $policy;
    private CurrentOperatorProviderInterface|MockObject $currentOperatorProvider;
    private NotificationDispatcherInterface|MockObject $notificationDispatcher;

    protected function setUp(): void
    {
        $this->auditRepository = $this->createMock(MessageAuditRepositoryInterface::class);
        $this->operatorActionRepository = $this->createMock(OperatorActionRepositoryInterface::class);
        $this->executor = $this->createMock(OperatorActionExecutorInterface::class);
        $this->policy = $this->createMock(OperatorPolicyInterface::class);
        $this->currentOperatorProvider = $this->createMock(CurrentOperatorProviderInterface::class);
        $this->notificationDispatcher = $this->createMock(NotificationDispatcherInterface::class);

        $this->operatorActionService = new OperatorActionService(
            $this->auditRepository,
            $this->operatorActionRepository,
            $this->executor,
            $this->policy,
            $this->currentOperatorProvider,
            $this->notificationDispatcher
        );
    }

    public function testRetryNowSuccessfully(): void
    {
        $messageUuid = 'test-message-uuid';
        $reasonCode = 'test-reason-code';
        $operatorIdentity = $this->createOperatorIdentity();
        $snapshot = $this->createMessageSnapshot($messageUuid);
        $actionResult = $this->createActionResult($messageUuid);

        $this->expectRetryNowDependencies(
            $messageUuid,
            $reasonCode,
            $snapshot,
            $operatorIdentity,
            $actionResult,
        );

        $result = $this->operatorActionService->retryNow($messageUuid, $reasonCode);

        self::assertSame($actionResult, $result);
    }

    public function testQuarantineSuccessfullyUpdatesSnapshot(): void
    {
        $this->assertActionUpdatesSnapshot(
            actionType: 'quarantine',
            reasonCode: 'quarantine',
            snapshot: $this->createMessageSnapshot('test-message-uuid'),
            actionResult: new OperatorActionResult('test-action-uuid', 'accepted', 'test-message-uuid', 'quarantined'),
            snapshotAssertion: static fn (MessageSnapshot $updated): bool => $updated->status === 'quarantined' && $updated->isQuarantined,
        );
    }

    public function testDismissSuccessfullyUpdatesSnapshot(): void
    {
        $this->assertActionUpdatesSnapshot(
            actionType: 'dismiss',
            reasonCode: 'dismiss',
            snapshot: $this->createMessageSnapshot('test-message-uuid', status: 'quarantined', isQuarantined: true),
            actionResult: new OperatorActionResult('test-action-uuid', 'accepted', 'test-message-uuid', 'dismissed'),
            snapshotAssertion: static fn (MessageSnapshot $updated): bool => $updated->status === 'dismissed' && ! $updated->isQuarantined,
        );
    }

    public function testRetryNowThrowsExceptionIfMessageNotFound(): void
    {
        $messageUuid = 'unknown-message-uuid';
        $reasonCode = 'test-reason-code';

        $this->auditRepository
            ->expects(self::once())
            ->method('findByMessageUuid')
            ->with($messageUuid)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown message UUID.');

        $this->operatorActionService->retryNow($messageUuid, $reasonCode);
    }

    public function testRetryNowThrowsExceptionIfPolicyDisallowsAction(): void
    {
        $messageUuid = 'test-message-uuid';
        $reasonCode = 'test-reason-code';
        $snapshot = $this->createMessageSnapshot($messageUuid);

        $this->expectRetryPolicyDenied(
            $messageUuid,
            $snapshot,
            $this->createOperatorIdentity(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Policy forbids it.');

        $this->operatorActionService->retryNow($messageUuid, $reasonCode);
    }

    private function createOperatorIdentity(): OperatorIdentity
    {
        return new OperatorIdentity('test-user-id', 'test@example.com', 'Test Operator');
    }

    private function createMessageSnapshot(
        string $messageUuid,
        string $status = 'failed',
        bool $isQuarantined = false,
    ): MessageSnapshot {
        $now = new \DateTimeImmutable();
        $defaults = $this->createMessageSnapshotDefaults($status, $isQuarantined);

        return new MessageSnapshot(
            messageUuid: $messageUuid,
            messageClass: $defaults['messageClass'],
            messageName: $defaults['messageName'],
            busName: $defaults['busName'],
            transportName: $defaults['transportName'],
            transportMessageId: $defaults['transportMessageId'],
            correlationId: $defaults['correlationId'],
            causationId: $defaults['causationId'],
            businessType: $defaults['businessType'],
            businessReference: $defaults['businessReference'],
            status: $defaults['status'],
            retryCount: $defaults['retryCount'],
            isQuarantined: $defaults['isQuarantined'],
            payload: $defaults['payload'],
            payloadPreview: $defaults['payloadPreview'],
            headersPreview: $defaults['headersPreview'],
            firstSeenAt: $now,
            lastSeenAt: $now,
            firstHandledAt: null,
            finishedAt: null,
        );
    }

    private function createActionResult(string $messageUuid): OperatorActionResult
    {
        return new OperatorActionResult(
            actionUuid: 'test-action-uuid',
            result: 'accepted',
            messageUuid: $messageUuid,
            newStatus: 'retry_requested',
            details: [],
        );
    }

    private function createPolicyDecision(bool $isAllowed, ?string $reason): PolicyDecisionInterface
    {
        return $this->createConfiguredMock(PolicyDecisionInterface::class, [
            'isAllowed' => $isAllowed,
            'getReason' => $reason,
        ]);
    }

    private function expectRetryNowDependencies(
        string $messageUuid,
        string $reasonCode,
        MessageSnapshot $snapshot,
        OperatorIdentity $operatorIdentity,
        OperatorActionResult $actionResult,
    ): void {
        $this->expectActionDependencies(
            actionType: 'retry_now',
            messageUuid: $messageUuid,
            reasonCode: $reasonCode,
            snapshot: $snapshot,
            operatorIdentity: $operatorIdentity,
            actionResult: $actionResult,
        );
    }

    private function expectActionDependencies(
        string $actionType,
        string $messageUuid,
        string $reasonCode,
        MessageSnapshot $snapshot,
        OperatorIdentity $operatorIdentity,
        OperatorActionResult $actionResult,
    ): void {
        $this->expectMessageLookup($messageUuid, $snapshot);
        $this->expectCurrentOperatorLookup($operatorIdentity);
        $this->expectPolicyDecision(true, null);
        $this->expectExecutorCall($actionType, $actionResult);
        $this->expectTransitionAppend();
        $this->expectActionRecordAppend(
            $actionType,
            $messageUuid,
            $reasonCode,
            $snapshot,
            $operatorIdentity,
            $actionResult,
        );
        $this->expectNotificationDispatch($actionType, $actionResult, $messageUuid, $reasonCode);
    }

    private function expectRetryPolicyDenied(
        string $messageUuid,
        MessageSnapshot $snapshot,
        OperatorIdentity $operatorIdentity,
    ): void {
        $this->expectMessageLookup($messageUuid, $snapshot);
        $this->expectCurrentOperatorLookup($operatorIdentity);
        $this->expectPolicyDecision(false, 'Policy forbids it.');
    }

    private function expectActionRecordAppend(
        string $actionType,
        string $messageUuid,
        string $reasonCode,
        MessageSnapshot $snapshot,
        OperatorIdentity $operatorIdentity,
        OperatorActionResult $actionResult,
    ): void {
        $matchesRecord = static function (OperatorActionRecord $record) use (
            $actionType,
            $messageUuid,
            $reasonCode,
            $operatorIdentity,
            $snapshot,
            $actionResult,
        ): bool {
            return $record->getActionUuid() === $actionResult->getActionUuid()
                && $record->getMessageUuid() === $messageUuid
                && $record->getActionType() === $actionType
                && $record->getOldStatus() === $snapshot->status
                && $record->getNewStatus() === $actionResult->getNewStatus()
                && $record->getOperator() === $operatorIdentity
                && $record->getReasonCode() === $reasonCode
                && $record->getResult() === $actionResult->getResult();
        };

        $this->operatorActionRepository
            ->expects(self::once())
            ->method('append')
            ->with(self::callback($matchesRecord));
    }

    private function expectNotificationDispatch(
        string $actionType,
        OperatorActionResult $actionResult,
        string $messageUuid,
        string $reasonCode,
    ): void {
        $matchesEvent = static function (NotificationEvent $event) use (
            $actionType,
            $actionResult,
            $messageUuid,
            $reasonCode,
        ): bool {
            return $event->eventName === 'operator.action.executed'
                && $event->payload['actionUuid'] === $actionResult->getActionUuid()
                && $event->payload['messageUuid'] === $messageUuid
                && $event->payload['actionType'] === $actionType
                && $event->payload['reasonCode'] === $reasonCode
                && $event->payload['result'] === $actionResult->getResult();
        };

        $this->notificationDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback($matchesEvent));
    }

    private function expectMessageLookup(string $messageUuid, MessageSnapshot $snapshot): void
    {
        $this->auditRepository
            ->expects(self::once())
            ->method('findByMessageUuid')
            ->with($messageUuid)
            ->willReturn($snapshot);
    }

    private function expectCurrentOperatorLookup(OperatorIdentity $operatorIdentity): void
    {
        $this->currentOperatorProvider
            ->expects(self::once())
            ->method('getCurrentOperator')
            ->willReturn($operatorIdentity);
    }

    private function expectPolicyDecision(bool $isAllowed, ?string $reason): void
    {
        $this->policy
            ->expects(self::once())
            ->method('canExecute')
            ->willReturn($this->createPolicyDecision($isAllowed, $reason));
    }

    private function expectExecutorCall(string $actionType, OperatorActionResult $actionResult): void
    {
        $this->resolveExecutorExpectation($actionType)->willReturn($actionResult);
    }

    /**
     * @param callable(MessageSnapshot): bool $snapshotAssertion
     */
    private function assertActionUpdatesSnapshot(
        string $actionType,
        string $reasonCode,
        MessageSnapshot $snapshot,
        OperatorActionResult $actionResult,
        callable $snapshotAssertion,
    ): void {
        $operatorIdentity = $this->createOperatorIdentity();
        $this->expectActionDependencies(
            actionType: $actionType,
            messageUuid: $snapshot->messageUuid,
            reasonCode: $reasonCode,
            snapshot: $snapshot,
            operatorIdentity: $operatorIdentity,
            actionResult: $actionResult,
        );
        $this->expectSnapshotUpsert($snapshotAssertion);
        $this->executeAction($actionType, $snapshot->messageUuid, $reasonCode);
    }

    private function expectTransitionAppend(): void
    {
        $this->auditRepository
            ->expects(self::once())
            ->method('appendTransition');
    }

    private function expectSnapshotUpsert(callable $snapshotAssertion): void
    {
        $this->auditRepository
            ->expects(self::once())
            ->method('upsertMessage')
            ->with(self::callback($snapshotAssertion));
    }

    private function executeAction(string $actionType, string $messageUuid, string $reasonCode): void
    {
        match ($actionType) {
            'retry_now' => $this->operatorActionService->retryNow($messageUuid, $reasonCode),
            'quarantine' => $this->operatorActionService->quarantine($messageUuid, $reasonCode),
            'dismiss' => $this->operatorActionService->dismiss($messageUuid, $reasonCode),
            default => throw new \InvalidArgumentException('Unsupported action type: ' . $actionType),
        };
    }

    private function resolveExecutorExpectation(string $actionType): \PHPUnit\Framework\MockObject\Builder\InvocationMocker
    {
        return match ($actionType) {
            'retry_now' => $this->executor
                ->expects(self::once())
                ->method('retryNow'),
            'quarantine' => $this->executor
                ->expects(self::once())
                ->method('quarantine'),
            'dismiss' => $this->executor
                ->expects(self::once())
                ->method('dismiss'),
            default => throw new \InvalidArgumentException('Unsupported action type: ' . $actionType),
        };
    }

    /**
     * @return array{
     *     messageClass: string,
     *     messageName: string,
     *     busName: string,
     *     transportName: string,
     *     transportMessageId: string,
     *     correlationId: string,
     *     causationId: string,
     *     businessType: string,
     *     businessReference: string,
     *     status: string,
     *     retryCount: int,
     *     isQuarantined: bool,
     *     payload: array<string, array<array-key, scalar|null>|scalar|null>,
     *     payloadPreview: array<string, array<array-key, scalar|null>|scalar|null>,
     *     headersPreview: array<string, array<array-key, scalar|null>|scalar|null>
     * }
     */
    private function createMessageSnapshotDefaults(string $status, bool $isQuarantined): array
    {
        return [
            'messageClass' => 'TestMessageClass',
            'messageName' => 'test.message',
            'busName' => 'command_bus',
            'transportName' => 'async',
            'transportMessageId' => '123',
            'correlationId' => 'corr-123',
            'causationId' => 'caus-123',
            'businessType' => 'order',
            'businessReference' => 'ref-123',
            'status' => $status,
            'retryCount' => 0,
            'isQuarantined' => $isQuarantined,
            'payload' => [],
            'payloadPreview' => [],
            'headersPreview' => [],
        ];
    }
}
