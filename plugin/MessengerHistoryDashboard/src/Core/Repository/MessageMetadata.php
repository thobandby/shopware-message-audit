<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Repository;

final readonly class MessageMetadata
{
    public function __construct(
        public ?string $correlationId = null,
        public ?string $causationId = null,
        public ?string $transportName = null,
        public ?string $businessReference = null
    ) {
    }
}
