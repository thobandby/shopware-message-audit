<?php declare(strict_types=1);

namespace MessengerHistoryDashboard\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1725000004CreateMhSchema extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1725000004;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `mh_message` (
                `id` VARCHAR(64) NOT NULL,
                `message_class` VARCHAR(255) NOT NULL,
                `payload_json` LONGTEXT NULL,
                `status` VARCHAR(32) NOT NULL,
                `retry_count` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx.mh_message.status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `mh_transition` (
                `id` VARCHAR(64) NOT NULL,
                `message_id` VARCHAR(64) NOT NULL,
                `event` VARCHAR(64) NOT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx.mh_transition.message_id` (`message_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `mh_failure` (
                `id` VARCHAR(64) NOT NULL,
                `message_id` VARCHAR(64) NOT NULL,
                `exception_class` VARCHAR(255) NOT NULL,
                `error_message` LONGTEXT NOT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx.mh_failure.message_id` (`message_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `mh_operator_action` (
                `id` VARCHAR(64) NOT NULL,
                `message_id` VARCHAR(64) NOT NULL,
                `action` VARCHAR(64) NOT NULL,
                `reason` LONGTEXT NOT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx.mh_operator_action.message_id` (`message_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement(
            'ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `retry_count` INT NOT NULL DEFAULT 0'
        );

        if (!$this->hasIndex($connection, 'mh_message', 'idx.mh_message.status')) {
            $connection->executeStatement(
                'CREATE INDEX `idx.mh_message.status` ON `mh_message` (`status`)'
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
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
