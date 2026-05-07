<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Observer;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Thorsten\MessengerHistory\Messenger\Contract\MessageAuditRepositoryInterface;
use Thorsten\MessengerHistory\Messenger\Contract\MessageCorrelationResolverInterface;
use Thorsten\MessengerHistory\Messenger\Contract\MessagePayloadExtractorInterface;
use Thorsten\MessengerHistory\Messenger\Model\MessageSnapshot;
use Thorsten\MessengerHistory\Messenger\Model\TransitionRecord;

final readonly class MessageAuditMiddleware implements MiddlewareInterface
{
    public function __construct(
        private MessageAuditRepositoryInterface $auditRepository,
        private MessageCorrelationResolverInterface $correlationResolver,
        private MessagePayloadExtractorInterface $payloadExtractor,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $now = new \DateTimeImmutable();
        $messageUuid = $this->correlationResolver->resolveMessageUuid($envelope);

        $this->auditRepository->upsertMessage(new MessageSnapshot(
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
            payload: $this->payloadExtractor->extractPayload($envelope),
            payloadPreview: $this->payloadExtractor->extractPayloadPreview($envelope),
            headersPreview: $this->payloadExtractor->extractHeadersPreview($envelope),
            firstSeenAt: $now,
            lastSeenAt: $now,
            firstHandledAt: null,
            finishedAt: null,
        ));

        $this->auditRepository->appendTransition(new TransitionRecord(
            messageUuid: $messageUuid,
            fromStatus: null,
            toStatus: 'dispatched',
            eventName: 'middleware.dispatch',
            transportName: null,
            workerName: null,
            durationMs: null,
            details: [],
            occurredAt: $now,
        ));

        return $stack->next()->handle($envelope, $stack);
    }
}
