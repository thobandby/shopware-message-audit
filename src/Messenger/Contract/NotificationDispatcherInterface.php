<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Contract;

use Thorsten\MessengerHistory\Messenger\Model\NotificationEvent;

interface NotificationDispatcherInterface
{
    public function dispatch(NotificationEvent $event): void;
}
