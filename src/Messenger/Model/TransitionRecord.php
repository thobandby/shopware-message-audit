<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Model;

final class TransitionRecord
{
    /**
     * @param array<string, array<array-key, scalar|null>|scalar|null> $details
     */
    public function __construct(
        private readonly string $messageUuid,
        private readonly ?string $fromStatus,
        private readonly string $toStatus,
        private readonly string $eventName,
        private readonly ?string $transportName,
        private readonly ?string $workerName,
        private readonly ?int $durationMs,
        private readonly array $details,
        private readonly \DateTimeImmutable $occurredAt,
    ) {
    }

    public function getMessageUuid(): string
    {
        return $this->messageUuid;
    }

    public function getFromStatus(): ?string
    {
        return $this->fromStatus;
    }

    public function getToStatus(): string
    {
        return $this->toStatus;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function getTransportName(): ?string
    {
        return $this->transportName;
    }

    public function getWorkerName(): ?string
    {
        return $this->workerName;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    /**
     * @return array<string, array<array-key, scalar|null>|scalar|null>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
