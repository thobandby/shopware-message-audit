<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Operator;

use Thorsten\MessengerHistory\Messenger\Contract\CurrentOperatorProviderInterface;
use Thorsten\MessengerHistory\Messenger\Contract\MessageAuditRepositoryInterface;
use Thorsten\MessengerHistory\Messenger\Contract\NotificationDispatcherInterface;
use Thorsten\MessengerHistory\Messenger\Contract\OperatorActionExecutorInterface;
use Thorsten\MessengerHistory\Messenger\Contract\OperatorActionRepositoryInterface;
use Thorsten\MessengerHistory\Messenger\Contract\OperatorPolicyInterface;
use Thorsten\MessengerHistory\Messenger\Model\MessageSnapshot;
use Thorsten\MessengerHistory\Messenger\Model\NotificationEvent;
use Thorsten\MessengerHistory\Messenger\Model\OperatorActionRecord;
use Thorsten\MessengerHistory\Messenger\Model\OperatorActionRequest;
use Thorsten\MessengerHistory\Messenger\Model\OperatorActionResult;

final class OperatorActionService
{
    /** @readonly */
    private MessageAuditRepositoryInterface $auditRepository;

    /** @readonly */
    private OperatorActionRepositoryInterface $operatorActionRepository;

    /** @readonly */
    private OperatorActionExecutorInterface $executor;

    /** @readonly */
    private OperatorPolicyInterface $policy;

    /** @readonly */
    private CurrentOperatorProviderInterface $currentOperatorProvider;

    /** @readonly */
    private NotificationDispatcherInterface $notificationDispatcher;

    public function __construct(
        MessageAuditRepositoryInterface $auditRepository,
        OperatorActionRepositoryInterface $operatorActionRepository,
        OperatorActionExecutorInterface $executor,
        OperatorPolicyInterface $policy,
        CurrentOperatorProviderInterface $currentOperatorProvider,
        NotificationDispatcherInterface $notificationDispatcher,
    ) {
        $this->auditRepository = $auditRepository;
        $this->operatorActionRepository = $operatorActionRepository;
        $this->executor = $executor;
        $this->policy = $policy;
        $this->currentOperatorProvider = $currentOperatorProvider;
        $this->notificationDispatcher = $notificationDispatcher;
    }

    public function retryNow(string $messageUuid, string $reasonCode, ?string $reasonText = null): OperatorActionResult
    {
        return $this->executeAction($messageUuid, 'retry_now', $reasonCode, $reasonText);
    }

    public function quarantine(string $messageUuid, string $reasonCode, ?string $reasonText = null): OperatorActionResult
    {
        return $this->executeAction($messageUuid, 'quarantine', $reasonCode, $reasonText);
    }

    public function dismiss(string $messageUuid, string $reasonCode, ?string $reasonText = null): OperatorActionResult
    {
        return $this->executeAction($messageUuid, 'dismiss', $reasonCode, $reasonText);
    }

    private function executeAction(
        string $messageUuid,
        string $actionType,
        string $reasonCode,
        ?string $reasonText,
    ): OperatorActionResult {
        $request = $this->createRequest($messageUuid, $actionType, $reasonCode, $reasonText);
        $snapshot = $this->requireSnapshot($messageUuid);
        $this->assertActionIsAllowed($request, $snapshot);
        $result = $this->executeRequest($actionType, $request);

        if ($result->getResult() === 'accepted') {
            $this->persistSnapshotChange($snapshot, $actionType, $result);
        }

        $this->persistOperatorAction($snapshot, $request, $result);
        $this->dispatchNotification($messageUuid, $actionType, $reasonCode, $result);

        return $result;
    }

    private function createRequest(
        string $messageUuid,
        string $actionType,
        string $reasonCode,
        ?string $reasonText,
    ): OperatorActionRequest {
        return new OperatorActionRequest(
            messageUuid: $messageUuid,
            actionType: $actionType,
            reasonCode: $reasonCode,
            reasonText: $reasonText,
            expectedStatus: null,
            operator: $this->currentOperatorProvider->getCurrentOperator(),
        );
    }

    private function requireSnapshot(string $messageUuid): MessageSnapshot
    {
        $snapshot = $this->auditRepository->findByMessageUuid($messageUuid);
        if ($snapshot instanceof MessageSnapshot) {
            return $snapshot;
        }

        throw new \RuntimeException('Unknown message UUID.');
    }

    private function assertActionIsAllowed(OperatorActionRequest $request, MessageSnapshot $snapshot): void
    {
        $decision = $this->policy->canExecute($request, $snapshot);
        if ($decision->isAllowed()) {
            return;
        }

        throw new \RuntimeException($decision->getReason() ?? 'Action is not allowed.');
    }

    private function executeRequest(string $actionType, OperatorActionRequest $request): OperatorActionResult
    {
        return match ($actionType) {
            'retry_now' => $this->executor->retryNow($request),
            'quarantine' => $this->executor->quarantine($request),
            'dismiss' => $this->executor->dismiss($request),
            default => throw new \InvalidArgumentException('Unsupported action type: ' . $actionType),
        };
    }

    private function persistOperatorAction(
        MessageSnapshot $snapshot,
        OperatorActionRequest $request,
        OperatorActionResult $result,
    ): void {
        $this->operatorActionRepository->append(new OperatorActionRecord(
            actionUuid: $result->getActionUuid(),
            messageUuid: $snapshot->messageUuid,
            actionType: $request->actionType,
            oldStatus: $snapshot->status,
            newStatus: $result->getNewStatus(),
            oldTransport: $snapshot->transportName,
            newTransport: $snapshot->transportName,
            operator: $request->operator,
            reasonCode: $request->reasonCode,
            reasonText: $request->reasonText,
            requestId: null,
            uiSessionId: null,
            overridePayload: null,
            result: $result->getResult(),
            resultDetails: $result->getDetails(),
            approvedByUserId: null,
            approvedAt: null,
            executedAt: new \DateTimeImmutable(),
        ));
    }

    private function dispatchNotification(
        string $messageUuid,
        string $actionType,
        string $reasonCode,
        OperatorActionResult $result,
    ): void {
        $this->notificationDispatcher->dispatch(new NotificationEvent(
            eventName: 'operator.action.executed',
            occurredAt: new \DateTimeImmutable(),
            payload: [
                'actionUuid' => $result->getActionUuid(),
                'messageUuid' => $messageUuid,
                'actionType' => $actionType,
                'reasonCode' => $reasonCode,
                'result' => $result->getResult(),
            ],
        ));
    }

    private function persistSnapshotChange(
        MessageSnapshot $snapshot,
        string $actionType,
        OperatorActionResult $result,
    ): void {
        $newStatus = $result->getNewStatus();
        if ($newStatus === null) {
            return;
        }

        $now = new \DateTimeImmutable();
        $isQuarantined = match ($actionType) {
            'quarantine' => true,
            'dismiss' => false,
            default => $snapshot->isQuarantined,
        };
        $retryCount = $actionType === 'retry_now' ? $snapshot->retryCount + 1 : $snapshot->retryCount;

        $this->auditRepository->appendTransition(new \Thorsten\MessengerHistory\Messenger\Model\TransitionRecord(
            messageUuid: $snapshot->messageUuid,
            fromStatus: $snapshot->status,
            toStatus: $newStatus,
            eventName: 'operator.' . $actionType,
            transportName: $snapshot->transportName,
            workerName: null,
            durationMs: null,
            details: $result->getDetails(),
            occurredAt: $now,
        ));

        $this->auditRepository->upsertMessage(new MessageSnapshot(
            messageUuid: $snapshot->messageUuid,
            messageClass: $snapshot->messageClass,
            messageName: $snapshot->messageName,
            busName: $snapshot->busName,
            transportName: $snapshot->transportName,
            transportMessageId: $snapshot->transportMessageId,
            correlationId: $snapshot->correlationId,
            causationId: $snapshot->causationId,
            businessType: $snapshot->businessType,
            businessReference: $snapshot->businessReference,
            status: $newStatus,
            retryCount: $retryCount,
            isQuarantined: $isQuarantined,
            payload: $snapshot->payload,
            payloadPreview: $snapshot->payloadPreview,
            headersPreview: $snapshot->headersPreview,
            firstSeenAt: $snapshot->firstSeenAt,
            lastSeenAt: $now,
            firstHandledAt: $snapshot->firstHandledAt,
            finishedAt: $snapshot->finishedAt,
        ));
    }
}
