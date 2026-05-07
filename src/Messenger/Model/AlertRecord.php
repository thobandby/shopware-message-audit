<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Model;

final readonly class AlertRecord
{
    public function __construct(
        public string $code,
        public string $severity,
        public string $message,
        public \DateTimeImmutable $startedAt,
    ) {
    }
}
