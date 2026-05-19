<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Service;

use MessengerHistoryDashboard\Core\Messenger\Stamp\CausationIdStamp;
use MessengerHistoryDashboard\Core\Messenger\Stamp\CorrelationIdStamp;
use MessengerHistoryDashboard\Core\Repository\FailureRepository;
use MessengerHistoryDashboard\Core\Repository\MessageMetadata;
use MessengerHistoryDashboard\Core\Repository\MessageRepository;
use MessengerHistoryDashboard\Core\Repository\TransitionRepository;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

final class MessageAuditWriter
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly TransitionRepository $transitionRepository,
        private readonly FailureRepository $failureRepository,
        private readonly PayloadSerializer $payloadSerializer
    ) {
    }

    public function onDispatched(string $uuid, object $message, Envelope $envelope): void
    {
        if (! $this->messageRepository->hasAuditSchema()) {
            return;
        }

        $this->ensureMessageExists($uuid, $message, 'dispatched', $envelope);

        $this->transitionRepository->insert($uuid, 'dispatched');
    }

    public function onReceived(string $uuid, Envelope $envelope): void
    {
        if (! $this->messageRepository->hasAuditSchema()) {
            return;
        }

        $this->ensureMessageExists($uuid, $envelope->getMessage(), 'received', $envelope);
        $this->messageRepository->updateStatus($uuid, 'received');
        $this->messageRepository->syncRetryCount($uuid, $this->resolveRetryCount($envelope));
        $this->messageRepository->updateMetadata($uuid, $this->createMetadata($envelope, $uuid));
        $this->transitionRepository->insert($uuid, 'received');
    }

    public function onHandled(string $uuid, Envelope $envelope): void
    {
        if (! $this->messageRepository->hasAuditSchema()) {
            return;
        }

        $this->ensureMessageExists($uuid, $envelope->getMessage(), 'handled', $envelope);
        $this->messageRepository->updateStatus($uuid, 'handled');
        $this->messageRepository->syncRetryCount($uuid, $this->resolveRetryCount($envelope));
        $this->messageRepository->updateMetadata($uuid, $this->createMetadata($envelope, $uuid));
        $this->transitionRepository->insert($uuid, 'handled');
        $this->messageRepository->cleanupShopwareMessengerEntriesOlderThan24Hours();
    }

    public function onFailed(string $uuid, Envelope $envelope, \Throwable $exception): void
    {
        if (! $this->messageRepository->hasAuditSchema()) {
            return;
        }

        $this->ensureMessageExists($uuid, $envelope->getMessage(), 'failed', $envelope);
        $this->messageRepository->updateStatus($uuid, 'failed');
        $this->messageRepository->syncRetryCount($uuid, $this->resolveRetryCount($envelope));
        $this->messageRepository->updateMetadata($uuid, $this->createMetadata($envelope, $uuid));
        $this->transitionRepository->insert($uuid, 'failed');
        $this->failureRepository->insert($uuid, $exception::class, $exception->getMessage());
    }

    public function recordRetry(string $messageId): void
    {
        if (! $this->messageRepository->hasAuditSchema()) {
            return;
        }

        $this->transitionRepository->insert($messageId, 'retry_now');
    }

    /**
     * @param array<string, array<array-key, scalar|null>|scalar|null> $payload
     */
    public function recordStateChange(
        string $id,
        string $entryClass,
        string $status,
        array $payload,
        MessageMetadata $metadata,
        string $transitionEvent
    ): void {
        if (! $this->messageRepository->hasAuditSchema()) {
            return;
        }

        $this->messageRepository->insertIfMissing(
            $id,
            $entryClass,
            json_encode($payload, JSON_THROW_ON_ERROR),
            $status,
            $metadata
        );
        $this->messageRepository->updateStatus($id, $status);
        $this->messageRepository->updateMetadata($id, $metadata);
        $this->transitionRepository->insert($id, $transitionEvent);
    }

    private function ensureMessageExists(string $uuid, object $message, string $status, Envelope $envelope): void
    {
        $payload = $this->payloadSerializer->serialize($message);

        $this->messageRepository->insertIfMissing(
            $uuid,
            $message::class,
            json_encode($payload, JSON_THROW_ON_ERROR),
            $status,
            $this->createMetadata($envelope, $uuid, $payload)
        );
    }

    /**
     * @param array<string, array<array-key, scalar|null>|scalar|null>|null $payload
     */
    private function createMetadata(
        Envelope $envelope,
        ?string $fallbackCorrelationId = null,
        ?array $payload = null
    ): MessageMetadata {
        $serializedPayload = $payload ?? $this->payloadSerializer->serialize($envelope->getMessage());

        return new MessageMetadata(
            $this->resolveCorrelationId($envelope, $fallbackCorrelationId ?? ''),
            $this->resolveCausationId($envelope),
            $this->resolveTransportName($envelope),
            $this->resolveBusinessReference($serializedPayload)
        );
    }

    private function resolveCorrelationId(Envelope $envelope, string $uuid): string
    {
        $stamp = $envelope->last(CorrelationIdStamp::class);

        if ($stamp instanceof CorrelationIdStamp) {
            return $stamp->getCorrelationId();
        }

        return $uuid;
    }

    private function resolveCausationId(Envelope $envelope): ?string
    {
        $stamp = $envelope->last(CausationIdStamp::class);

        return $stamp instanceof CausationIdStamp ? $stamp->getCausationId() : null;
    }

    private function resolveTransportName(Envelope $envelope): ?string
    {
        $stamp = $envelope->last(ReceivedStamp::class);

        return $stamp instanceof ReceivedStamp ? $stamp->getTransportName() : null;
    }

    private function resolveRetryCount(Envelope $envelope): int
    {
        $stamp = $envelope->last(RedeliveryStamp::class);

        return $stamp instanceof RedeliveryStamp ? $stamp->getRetryCount() : 0;
    }

    /**
     * @param array<string, array<array-key, scalar|null>|scalar|null> $payload
     */
    private function resolveBusinessReference(array $payload): ?string
    {
        foreach (['businessReference', 'orderNumber', 'orderId', 'paymentId', 'reference', 'id'] as $key) {
            $value = $payload[$key] ?? null;

            if (\is_scalar($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return null;
    }
}
