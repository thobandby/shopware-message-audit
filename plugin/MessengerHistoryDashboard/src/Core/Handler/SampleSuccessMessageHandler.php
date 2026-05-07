<?php declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Handler;

use MessengerHistoryDashboard\Core\Message\SampleSuccessMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SampleSuccessMessageHandler
{
    public function __invoke(SampleSuccessMessage $message): void
    {
        // success
    }
}
