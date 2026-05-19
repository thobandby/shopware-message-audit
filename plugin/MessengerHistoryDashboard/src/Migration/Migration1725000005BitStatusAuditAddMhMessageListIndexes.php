<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1725000005BitStatusAuditAddMhMessageListIndexes extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1725000005;
    }

    public function update(Connection $connection): void
    {
        if (! $this->hasIndex($connection, 'mh_message', 'idx.mh_message.created_at')) {
            $connection->executeStatement(
                'CREATE INDEX `idx.mh_message.created_at` ON `mh_message` (`created_at`)'
            );
        }

        if (! $this->hasIndex($connection, 'mh_message', 'idx.mh_message.status_created_at')) {
            $connection->executeStatement(
                'CREATE INDEX `idx.mh_message.status_created_at` ON `mh_message` (`status`, `created_at`)'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        unset($connection);
    }

    private function hasIndex(Connection $connection, string $tableName, string $indexName): bool
    {
        $indexExists = $connection->fetchOne(
            'SELECT COUNT(1) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
             AND table_name = :tableName
             AND index_name = :indexName',
            [
                'tableName' => $tableName,
                'indexName' => $indexName,
            ]
        );

        return (int) $indexExists > 0;
    }
}
