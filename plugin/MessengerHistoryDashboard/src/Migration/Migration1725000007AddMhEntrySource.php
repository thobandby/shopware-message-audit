<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1725000007AddMhEntrySource extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1725000007;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            "ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `source` VARCHAR(32) NOT NULL DEFAULT 'messenger' AFTER `message_class`"
        );
        $connection->executeStatement(
            'ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `subject_type` VARCHAR(64) NULL AFTER `source`'
        );
        $connection->executeStatement(
            'ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `subject_id` VARCHAR(64) NULL AFTER `subject_type`'
        );

        $this->createIndexIfMissing($connection, 'mh_message', 'idx.mh_message.source', 'source');
        $this->createIndexIfMissing($connection, 'mh_message', 'idx.mh_message.subject_type', 'subject_type');
        $this->createIndexIfMissing($connection, 'mh_message', 'idx.mh_message.subject_id', 'subject_id');
    }

    public function updateDestructive(Connection $connection): void
    {
        unset($connection);
    }

    private function createIndexIfMissing(
        Connection $connection,
        string $tableName,
        string $indexName,
        string $columnName
    ): void {
        $exists = $connection->fetchOne(
            'SELECT COUNT(1) FROM information_schema.statistics
             WHERE table_schema = DATABASE()
             AND table_name = :tableName
             AND index_name = :indexName',
            [
                'tableName' => $tableName,
                'indexName' => $indexName,
            ]
        );

        if ((int) $exists === 0) {
            $connection->executeStatement(
                \sprintf('CREATE INDEX `%s` ON `%s` (`%s`)', $indexName, $tableName, $columnName)
            );
        }
    }
}
