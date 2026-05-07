<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final readonly class CorrelationIdStamp implements StampInterface
{
    public function __construct(public string $correlationId)
    {
    }
}
