<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1725000006AddMhMetadata extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1725000006;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `correlation_id` VARCHAR(64) NULL AFTER `message_class`'
        );
        $connection->executeStatement(
            'ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `causation_id` VARCHAR(64) NULL AFTER `correlation_id`'
        );
        $connection->executeStatement(
            'ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `transport_name` VARCHAR(255) NULL AFTER `causation_id`'
        );
        $connection->executeStatement(
            'ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `business_reference` VARCHAR(255) NULL AFTER `transport_name`'
        );
        $connection->executeStatement(
            'ALTER TABLE `mh_operator_action` ADD COLUMN IF NOT EXISTS `operator_id` VARCHAR(64) NULL AFTER `reason`'
        );
        $connection->executeStatement(
            'ALTER TABLE `mh_operator_action` ADD COLUMN IF NOT EXISTS `operator_type` VARCHAR(64) NULL AFTER `operator_id`'
        );
        $connection->executeStatement(
            'ALTER TABLE `mh_operator_action` ADD COLUMN IF NOT EXISTS `operator_label` VARCHAR(255) NULL AFTER `operator_type`'
        );
        $connection->executeStatement(
            'ALTER TABLE `mh_operator_action` ADD COLUMN IF NOT EXISTS `operator_email` VARCHAR(255) NULL AFTER `operator_label`'
        );

        $this->createIndexIfMissing($connection, 'mh_message', 'idx.mh_message.correlation_id', 'correlation_id');
        $this->createIndexIfMissing($connection, 'mh_message', 'idx.mh_message.transport_name', 'transport_name');
        $this->createIndexIfMissing($connection, 'mh_message', 'idx.mh_message.business_reference', 'business_reference');
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
