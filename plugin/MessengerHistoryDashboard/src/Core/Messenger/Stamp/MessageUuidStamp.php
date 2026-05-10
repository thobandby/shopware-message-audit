<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class MessageUuidStamp implements StampInterface
{
    public function __construct(private readonly string $uuid)
    {
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }
}
