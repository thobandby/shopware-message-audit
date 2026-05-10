<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Handler;

use MessengerHistoryDashboard\Core\Message\SampleFailureMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SampleFailureMessageHandler
{
    public function __invoke(SampleFailureMessage $message): void
    {
        throw new \RuntimeException('Demo failure for reference ' . $message->reference);
    }
}
