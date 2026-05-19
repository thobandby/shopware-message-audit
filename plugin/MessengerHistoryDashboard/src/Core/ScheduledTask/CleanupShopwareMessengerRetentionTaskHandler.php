<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\ScheduledTask;

use MessengerHistoryDashboard\Core\Repository\MessageRepository;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: CleanupShopwareMessengerRetentionTask::class)]
final class CleanupShopwareMessengerRetentionTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly MessageRepository $messageRepository
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        if (! $this->messageRepository->hasAuditSchema()) {
            return;
        }

        $this->messageRepository->cleanupShopwareMessengerEntriesOlderThan24Hours();
    }
}
