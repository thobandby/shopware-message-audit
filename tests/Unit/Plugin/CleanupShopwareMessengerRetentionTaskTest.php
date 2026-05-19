<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Tests\Unit\Plugin;

use MessengerHistoryDashboard\Core\ScheduledTask\CleanupShopwareMessengerRetentionTask;
use MessengerHistoryDashboard\Core\ScheduledTask\CleanupShopwareMessengerRetentionTaskHandler;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

final class CleanupShopwareMessengerRetentionTaskTest extends AbstractSqliteRepositoryTestCase
{
    public function testScheduledTaskMetadataIsConfiguredAsExpected(): void
    {
        self::assertSame(
            'messenger_history_dashboard.cleanup_shopware_messenger_retention',
            CleanupShopwareMessengerRetentionTask::getTaskName()
        );
        self::assertSame(3600, CleanupShopwareMessengerRetentionTask::getDefaultInterval());
        self::assertTrue(CleanupShopwareMessengerRetentionTask::shouldRescheduleOnFailure());
    }

    public function testHandlerRemovesOnlyOldShopwareMessengerEntries(): void
    {
        $this->insertMessage(
            'old-shopware',
            'Shopware\\Core\\Content\\ProductExport\\ScheduledTask\\ProductExportGenerateTask',
            'messenger',
            'handled',
            '2026-05-17 09:00:00'
        );
        $this->insertMessage(
            'recent-shopware',
            'Shopware\\Core\\Content\\ProductExport\\ScheduledTask\\ProductExportGenerateTask',
            'messenger',
            'handled',
            '2999-05-18 11:00:00'
        );
        $this->insertMessage(
            'old-internal',
            'MessengerHistoryDashboard\\Core\\ScheduledTask\\CleanupShopwareMessengerRetentionTask',
            'messenger',
            'handled',
            '2026-05-17 09:00:00'
        );
        $this->insertMessage(
            'old-state-change',
            'Shopware\\Checkout\\Order\\StatusTransition',
            'state_change',
            'completed',
            '2026-05-17 09:00:00'
        );

        $handler = new CleanupShopwareMessengerRetentionTaskHandler(
            $this->createMock(EntityRepository::class),
            new NullLogger(),
            $this->createMessageRepository()
        );

        $handler->run();

        self::assertFalse($this->messageExists('old-shopware'));
        self::assertTrue($this->messageExists('recent-shopware'));
        self::assertTrue($this->messageExists('old-internal'));
        self::assertTrue($this->messageExists('old-state-change'));
    }
}
