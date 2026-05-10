<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Service;

final class PayloadSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function serialize(object $message): array
    {
        if (method_exists($message, 'toArray')) {
            return $message->toArray();
        }

        return get_object_vars($message);
    }
}
