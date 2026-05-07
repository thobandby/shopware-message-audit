<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Model;

final readonly class NotificationEvent
{
    /**
     * @param array<string, array<array-key, scalar|null>|scalar|null> $payload
     */
    public function __construct(
        public string $eventName,
        public \DateTimeImmutable $occurredAt,
        public array $payload,
    ) {
    }
}
