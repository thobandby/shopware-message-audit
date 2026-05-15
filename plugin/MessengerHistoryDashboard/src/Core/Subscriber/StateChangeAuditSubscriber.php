<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Subscriber;

use MessengerHistoryDashboard\Core\Repository\MessageMetadata;
use MessengerHistoryDashboard\Core\Repository\StateChangeContextRepository;
use MessengerHistoryDashboard\Core\Service\MessageAuditWriter;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class StateChangeAuditSubscriber implements EventSubscriberInterface
{
    private const TRACKED_STATES = [
        'order' => [
            OrderStates::STATE_OPEN,
            OrderStates::STATE_IN_PROGRESS,
            OrderStates::STATE_COMPLETED,
            OrderStates::STATE_CANCELLED,
        ],
        'order_transaction' => [
            OrderTransactionStates::STATE_OPEN,
            OrderTransactionStates::STATE_PAID,
            OrderTransactionStates::STATE_PARTIALLY_PAID,
            OrderTransactionStates::STATE_REFUNDED,
            OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
            OrderTransactionStates::STATE_CANCELLED,
            OrderTransactionStates::STATE_REMINDED,
            OrderTransactionStates::STATE_AUTHORIZED,
            OrderTransactionStates::STATE_FAILED,
            OrderTransactionStates::STATE_IN_PROGRESS,
            OrderTransactionStates::STATE_CHARGEBACK,
            OrderTransactionStates::STATE_UNCONFIRMED,
        ],
        'order_delivery' => [
            OrderDeliveryStates::STATE_OPEN,
            OrderDeliveryStates::STATE_PARTIALLY_SHIPPED,
            OrderDeliveryStates::STATE_SHIPPED,
            OrderDeliveryStates::STATE_RETURNED,
            OrderDeliveryStates::STATE_PARTIALLY_RETURNED,
            OrderDeliveryStates::STATE_CANCELLED,
        ],
    ];

    public function __construct(
        private readonly MessageAuditWriter $writer,
        private readonly StateChangeContextRepository $stateChangeContextRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            self::buildStateChangeEventName(OrderStates::STATE_MACHINE) => ['onStateChange', 100],
            self::buildStateChangeEventName(OrderTransactionStates::STATE_MACHINE) => ['onStateChange', 100],
            self::buildStateChangeEventName(OrderDeliveryStates::STATE_MACHINE) => ['onStateChange', 100],
        ];
    }

    public function onStateChange(StateMachineStateChangeEvent $event): void
    {
        if ($event->getTransitionSide() !== StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER) {
            return;
        }

        $subjectType = $event->getTransition()->getEntityName();
        $subjectId = strtolower($event->getTransition()->getEntityId());
        $status = $event->getNextState()->getTechnicalName();

        if (! isset(self::TRACKED_STATES[$subjectType]) || ! \in_array($status, self::TRACKED_STATES[$subjectType], true)) {
            return;
        }

        $previousStatus = $event->getPreviousState()->getTechnicalName();
        $transitionAction = $event->getTransition()->getTransitionName();
        $transitionEvent = $event->getStateEventName();
        $context = $this->stateChangeContextRepository->findBySubject($subjectType, $subjectId);
        $logContext = [
            'eventName' => $transitionEvent,
            'subjectType' => $subjectType,
            'subjectId' => $subjectId,
            'previousState' => $previousStatus,
            'nextState' => $status,
            'transitionAction' => $transitionAction,
            'orderId' => $context['orderId'],
            'businessReference' => $context['businessReference'],
        ];

        $payload = [
            'eventName' => $transitionEvent,
            'subjectType' => $subjectType,
            'subjectId' => $subjectId,
            'orderId' => $context['orderId'],
            'businessReference' => $context['businessReference'],
            'previousState' => $previousStatus,
            'nextState' => $status,
            'transitionAction' => $transitionAction,
        ];

        $entryId = $this->buildEntryId($subjectType, $subjectId, $status, $transitionAction);

        $this->logger->info('MessengerHistoryDashboard state change audit started', $logContext + [
            'entryId' => $entryId,
        ]);

        try {
            $this->writer->recordStateChange(
                $entryId,
                $this->resolveEntryClass($subjectType),
                $status,
                $payload,
                new MessageMetadata(
                    correlationId: $context['orderId'] ?? $subjectId,
                    businessReference: $context['businessReference'],
                    source: 'state_change',
                    subjectType: $subjectType,
                    subjectId: $subjectId
                ),
                $transitionEvent
            );
        } catch (\Throwable $exception) {
            $this->logger->error('MessengerHistoryDashboard state change audit failed', $logContext + [
                'entryId' => $entryId,
                'exceptionClass' => $exception::class,
                'exceptionMessage' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $this->logger->info('MessengerHistoryDashboard state change audit persisted', $logContext + [
            'entryId' => $entryId,
        ]);
    }

    private static function buildStateChangeEventName(string $stateMachineName): string
    {
        return 'state_machine.' . $stateMachineName . '_changed';
    }

    private function buildEntryId(string $subjectType, string $subjectId, string $status, string $transitionAction): string
    {
        return hash('sha256', $subjectType . ':' . $subjectId . ':' . $status . ':' . $transitionAction . ':' . microtime(true));
    }

    private function resolveEntryClass(string $subjectType): string
    {
        return match ($subjectType) {
            'order' => 'Shopware\\Checkout\\Order\\StatusTransition',
            'order_transaction' => 'Shopware\\Checkout\\OrderTransaction\\StatusTransition',
            'order_delivery' => 'Shopware\\Checkout\\OrderDelivery\\StatusTransition',
            default => 'Shopware\\System\\StateChange',
        };
    }
}
