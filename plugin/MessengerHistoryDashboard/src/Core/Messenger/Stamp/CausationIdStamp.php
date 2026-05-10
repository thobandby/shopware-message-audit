<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class CausationIdStamp implements StampInterface
{
    public function __construct(private readonly string $causationId)
    {
    }

    public function getCausationId(): string
    {
        return $this->causationId;
    }
}
