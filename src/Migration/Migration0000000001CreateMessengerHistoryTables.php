<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration0000000001CreateMessengerHistoryTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1000000001;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `mh_message` (
                `id` BINARY(16) NOT NULL,
                `message_uuid` VARCHAR(36) NOT NULL,
                `message_class` VARCHAR(255) NOT NULL,
                `status` VARCHAR(50) NOT NULL,
                `transport_name` VARCHAR(100),
                `retry_count` INT DEFAULT 0,
                `payload_preview` JSON,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3),
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.mh_message.message_uuid` (`message_uuid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `mh_transition` (
                `id` BINARY(16) NOT NULL,
                `message_uuid` VARCHAR(36) NOT NULL,
                `from_status` VARCHAR(50),
                `to_status` VARCHAR(50) NOT NULL,
                `event_name` VARCHAR(255) NOT NULL,
                `transport_name` VARCHAR(100),
                `worker_name` VARCHAR(255),
                `duration_ms` INT,
                `details` JSON,
                `occurred_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx.mh_transition.message_uuid` (`message_uuid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `mh_failure` (
                `id` BINARY(16) NOT NULL,
                `message_uuid` VARCHAR(36) NOT NULL,
                `exception_class` VARCHAR(255) NOT NULL,
                `exception_message` TEXT NOT NULL,
                `error_hash` VARCHAR(64) NOT NULL,
                `error_details` JSON,
                `stacktrace` LONGTEXT,
                `failed_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx.mh_failure.message_uuid` (`message_uuid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `mh_operator_action` (
                `id` BINARY(16) NOT NULL,
                `action_uuid` VARCHAR(36) NOT NULL,
                `message_uuid` VARCHAR(36) NOT NULL,
                `action_type` VARCHAR(50) NOT NULL,
                `old_status` VARCHAR(50),
                `new_status` VARCHAR(50),
                `old_transport` VARCHAR(100),
                `new_transport` VARCHAR(100),
                `operator_user_id` VARCHAR(36),
                `operator_display_name` VARCHAR(255),
                `reason_code` VARCHAR(100),
                `reason_text` TEXT,
                `result` VARCHAR(50) NOT NULL,
                `result_details` JSON,
                `executed_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.mh_operator_action.action_uuid` (`action_uuid`),
                KEY `idx.mh_operator_action.message_uuid` (`message_uuid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        unset($connection);
    }
}
