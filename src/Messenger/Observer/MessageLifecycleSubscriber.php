<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Observer;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Thorsten\MessengerHistory\Messenger\Contract\MessageAuditRepositoryInterface;
use Thorsten\MessengerHistory\Messenger\Contract\MessageCorrelationResolverInterface;
use Thorsten\MessengerHistory\Messenger\Model\FailureSnapshot;
use Thorsten\MessengerHistory\Messenger\Model\MessageSnapshot;
use Thorsten\MessengerHistory\Messenger\Model\TransitionRecord;

final readonly class MessageLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageAuditRepositoryInterface $auditRepository,
        private MessageCorrelationResolverInterface $correlationResolver,
    ) {
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => 'onReceived',
            WorkerMessageHandledEvent::class => 'onHandled',
            WorkerMessageFailedEvent::class => 'onFailed',
        ];
    }

    public function onReceived(WorkerMessageReceivedEvent $event): void
    {
        $this->recordStatusChange($event->getEnvelope(), 'received', 'worker.received');
    }

    public function onHandled(WorkerMessageHandledEvent $event): void
    {
        $this->recordStatusChange($event->getEnvelope(), 'handled', 'worker.handled');
    }

    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $messageUuid = $this->correlationResolver->resolveMessageUuid($envelope);
        $throwable = $event->getThrowable();
        $now = new \DateTimeImmutable();

        $this->recordStatusChange($envelope, 'failed', 'worker.failed', $now);
        $this->auditRepository->appendFailure(new FailureSnapshot(
            messageUuid: $messageUuid,
            exceptionClass: $throwable::class,
            exceptionMessage: $throwable->getMessage(),
            errorHash: hash('sha256', $throwable::class . '|' . $throwable->getMessage()),
            errorDetails: [],
            stacktrace: $throwable->getTraceAsString(),
            failedAt: $now,
        ));
    }

    private function recordStatusChange(
        Envelope $envelope,
        string $status,
        string $eventName,
        ?\DateTimeImmutable $occurredAt = null,
    ): void {
        $now = $occurredAt ?? new \DateTimeImmutable();
        $snapshot = $this->resolveSnapshot($envelope, $now);

        $this->auditRepository->appendTransition(new TransitionRecord(
            messageUuid: $snapshot->messageUuid,
            fromStatus: $snapshot->status,
            toStatus: $status,
            eventName: $eventName,
            transportName: $snapshot->transportName,
            workerName: null,
            durationMs: null,
            details: [],
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
            status: $status,
            retryCount: $snapshot->retryCount,
            isQuarantined: $snapshot->isQuarantined,
            payload: $snapshot->payload,
            payloadPreview: $snapshot->payloadPreview,
            headersPreview: $snapshot->headersPreview,
            firstSeenAt: $snapshot->firstSeenAt,
            lastSeenAt: $now,
            firstHandledAt: $status === 'handled'
                ? ($snapshot->firstHandledAt ?? $now)
                : $snapshot->firstHandledAt,
            finishedAt: \in_array($status, ['handled', 'failed'], true) ? $now : null,
        ));
    }

    private function resolveSnapshot(Envelope $envelope, \DateTimeImmutable $now): MessageSnapshot
    {
        $messageUuid = $this->correlationResolver->resolveMessageUuid($envelope);
        $snapshot = $this->auditRepository->findByMessageUuid($messageUuid);

        if ($snapshot instanceof MessageSnapshot) {
            return $snapshot;
        }

        return new MessageSnapshot(
            messageUuid: $messageUuid,
            messageClass: $envelope->getMessage()::class,
            messageName: null,
            busName: null,
            transportName: null,
            transportMessageId: null,
            correlationId: $this->correlationResolver->resolveCorrelationId($envelope),
            causationId: $this->correlationResolver->resolveCausationId($envelope),
            businessType: null,
            businessReference: null,
            status: 'dispatched',
            retryCount: 0,
            isQuarantined: false,
            payload: [],
            payloadPreview: [],
            headersPreview: [],
            firstSeenAt: $now,
            lastSeenAt: $now,
            firstHandledAt: null,
            finishedAt: null,
        );
    }
}
