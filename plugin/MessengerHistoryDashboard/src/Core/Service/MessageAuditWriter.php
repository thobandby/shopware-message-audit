<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Service;

use MessengerHistoryDashboard\Core\Repository\FailureRepository;
use MessengerHistoryDashboard\Core\Repository\MessageRepository;
use MessengerHistoryDashboard\Core\Repository\TransitionRepository;
use Symfony\Component\Messenger\Envelope;

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
        $this->ensureMessageExists($uuid, $message, 'dispatched');

        $this->transitionRepository->insert($uuid, 'dispatched');
    }

    public function onReceived(string $uuid, Envelope $envelope): void
    {
        $this->ensureMessageExists($uuid, $envelope->getMessage(), 'received');
        $this->messageRepository->updateStatus($uuid, 'received');
        $this->transitionRepository->insert($uuid, 'received');
    }

    public function onHandled(string $uuid, Envelope $envelope): void
    {
        $this->ensureMessageExists($uuid, $envelope->getMessage(), 'handled');
        $this->messageRepository->updateStatus($uuid, 'handled');
        $this->transitionRepository->insert($uuid, 'handled');
    }

    public function onFailed(string $uuid, Envelope $envelope, \Throwable $exception): void
    {
        $this->ensureMessageExists($uuid, $envelope->getMessage(), 'failed');
        $this->messageRepository->updateStatus($uuid, 'failed');
        $this->transitionRepository->insert($uuid, 'failed');
        $this->failureRepository->insert($uuid, \get_class($exception), $exception->getMessage());
    }

    public function recordRetry(string $messageId): void
    {
        $this->messageRepository->incrementRetryCount($messageId);
        $this->transitionRepository->insert($messageId, 'retry_now');
    }

    private function ensureMessageExists(string $uuid, object $message, string $status): void
    {
        $this->messageRepository->insertIfMissing(
            $uuid,
            \get_class($message),
            json_encode($this->payloadSerializer->serialize($message), JSON_THROW_ON_ERROR),
            $status
        );
    }
}
