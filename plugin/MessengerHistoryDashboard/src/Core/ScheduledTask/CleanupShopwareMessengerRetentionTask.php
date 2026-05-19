<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

final class CleanupShopwareMessengerRetentionTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'messenger_history_dashboard.cleanup_shopware_messenger_retention';
    }

    public static function getDefaultInterval(): int
    {
        return self::HOURLY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
