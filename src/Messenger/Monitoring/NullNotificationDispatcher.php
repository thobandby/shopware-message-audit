<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Monitoring;

use Thorsten\MessengerHistory\Messenger\Contract\NotificationDispatcherInterface;
use Thorsten\MessengerHistory\Messenger\Model\NotificationEvent;

final readonly class NullNotificationDispatcher implements NotificationDispatcherInterface
{
    public function dispatch(NotificationEvent $event): void
    {
        unset($event);
    }
}
