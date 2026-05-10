<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Service;

use MessengerHistoryDashboard\Core\Messenger\Stamp\CausationIdStamp;
use MessengerHistoryDashboard\Core\Messenger\Stamp\CorrelationIdStamp;
use MessengerHistoryDashboard\Core\Repository\FailureRepository;
use MessengerHistoryDashboard\Core\Repository\MessageRepository;
use MessengerHistoryDashboard\Core\Repository\TransitionRepository;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

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
        $this->ensureMessageExists($uuid, $message, 'dispatched', $envelope);

        $this->transitionRepository->insert($uuid, 'dispatched');
    }

    public function onReceived(string $uuid, Envelope $envelope): void
    {
        $this->ensureMessageExists($uuid, $envelope->getMessage(), 'received', $envelope);
        $this->messageRepository->updateStatus($uuid, 'received');
        $this->messageRepository->updateMetadata(
            $uuid,
            $this->resolveCorrelationId($envelope, $uuid),
            $this->resolveCausationId($envelope),
            $this->resolveTransportName($envelope),
            $this->resolveBusinessReference($this->payloadSerializer->serialize($envelope->getMessage()))
        );
        $this->transitionRepository->insert($uuid, 'received');
    }

    public function onHandled(string $uuid, Envelope $envelope): void
    {
        $this->ensureMessageExists($uuid, $envelope->getMessage(), 'handled', $envelope);
        $this->messageRepository->updateStatus($uuid, 'handled');
        $this->messageRepository->updateMetadata(
            $uuid,
            $this->resolveCorrelationId($envelope, $uuid),
            $this->resolveCausationId($envelope),
            $this->resolveTransportName($envelope),
            $this->resolveBusinessReference($this->payloadSerializer->serialize($envelope->getMessage()))
        );
        $this->transitionRepository->insert($uuid, 'handled');
    }

    public function onFailed(string $uuid, Envelope $envelope, \Throwable $exception): void
    {
        $this->ensureMessageExists($uuid, $envelope->getMessage(), 'failed', $envelope);
        $this->messageRepository->updateStatus($uuid, 'failed');
        $this->messageRepository->updateMetadata(
            $uuid,
            $this->resolveCorrelationId($envelope, $uuid),
            $this->resolveCausationId($envelope),
            $this->resolveTransportName($envelope),
            $this->resolveBusinessReference($this->payloadSerializer->serialize($envelope->getMessage()))
        );
        $this->transitionRepository->insert($uuid, 'failed');
        $this->failureRepository->insert($uuid, $exception::class, $exception->getMessage());
    }

    public function recordRetry(string $messageId): void
    {
        $this->messageRepository->incrementRetryCount($messageId);
        $this->transitionRepository->insert($messageId, 'retry_now');
    }

    private function ensureMessageExists(string $uuid, object $message, string $status, Envelope $envelope): void
    {
        $payload = $this->payloadSerializer->serialize($message);

        $this->messageRepository->insertIfMissing(
            $uuid,
            $message::class,
            json_encode($payload, JSON_THROW_ON_ERROR),
            $status,
            $this->resolveCorrelationId($envelope, $uuid),
            $this->resolveCausationId($envelope),
            $this->resolveTransportName($envelope),
            $this->resolveBusinessReference($payload)
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

    /**
     * @param array<string, mixed> $payload
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
