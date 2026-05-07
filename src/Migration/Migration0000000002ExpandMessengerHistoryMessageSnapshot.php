<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration0000000002ExpandMessengerHistoryMessageSnapshot extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1000000002;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `message_name` VARCHAR(255) NULL');
        $connection->executeStatement('ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `bus_name` VARCHAR(255) NULL');
        $connection->executeStatement('ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `transport_message_id` VARCHAR(255) NULL');
        $connection->executeStatement('ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `correlation_id` VARCHAR(255) NULL');
        $connection->executeStatement('ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `causation_id` VARCHAR(255) NULL');
        $connection->executeStatement('ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `business_type` VARCHAR(255) NULL');
        $connection->executeStatement('ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `business_reference` VARCHAR(255) NULL');
        $connection->executeStatement('ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `is_quarantined` TINYINT(1) NOT NULL DEFAULT 0');
        $connection->executeStatement('ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `payload_json` LONGTEXT NULL');
        $connection->executeStatement('ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `headers_preview` JSON NULL');
        $connection->executeStatement('ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `first_handled_at` DATETIME(3) NULL');
        $connection->executeStatement('ALTER TABLE `mh_message` ADD COLUMN IF NOT EXISTS `finished_at` DATETIME(3) NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
        unset($connection);
    }
}
