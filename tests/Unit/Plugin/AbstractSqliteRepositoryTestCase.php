<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Tests\Unit\Plugin;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use MessengerHistoryDashboard\Core\Operator\OperatorActionPolicy;
use MessengerHistoryDashboard\Core\Repository\FailureRepository;
use MessengerHistoryDashboard\Core\Repository\MessagePresentationFormatter;
use MessengerHistoryDashboard\Core\Repository\MessageRepository;
use MessengerHistoryDashboard\Core\Repository\MessageRowGrouper;
use MessengerHistoryDashboard\Core\Repository\MessageWhereClauseBuilder;
use MessengerHistoryDashboard\Core\Repository\TransitionRepository;
use MessengerHistoryDashboard\Core\Service\MessageAuditWriter;
use MessengerHistoryDashboard\Core\Service\PayloadSerializer;
use PHPUnit\Framework\TestCase;

abstract class AbstractSqliteRepositoryTestCase extends TestCase
{
    protected Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->createAuditTables();
    }

    protected function createAuditTables(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE mh_message (
                id VARCHAR(64) NOT NULL PRIMARY KEY,
                message_class VARCHAR(255) NOT NULL,
                source VARCHAR(32) NOT NULL DEFAULT "messenger",
                subject_type VARCHAR(64) DEFAULT NULL,
                subject_id VARCHAR(64) DEFAULT NULL,
                correlation_id VARCHAR(64) DEFAULT NULL,
                causation_id VARCHAR(64) DEFAULT NULL,
                transport_name VARCHAR(255) DEFAULT NULL,
                business_reference VARCHAR(255) DEFAULT NULL,
                payload_json TEXT DEFAULT NULL,
                status VARCHAR(32) NOT NULL,
                retry_count INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )'
        );
        $this->connection->executeStatement(
            'CREATE TABLE mh_transition (
                id VARCHAR(64) NOT NULL PRIMARY KEY,
                message_id VARCHAR(64) NOT NULL,
                event VARCHAR(64) NOT NULL,
                created_at DATETIME NOT NULL
            )'
        );
        $this->connection->executeStatement(
            'CREATE TABLE mh_failure (
                id VARCHAR(64) NOT NULL PRIMARY KEY,
                message_id VARCHAR(64) NOT NULL,
                exception_class VARCHAR(255) NOT NULL,
                error_message TEXT NOT NULL,
                created_at DATETIME NOT NULL
            )'
        );
        $this->connection->executeStatement(
            'CREATE TABLE mh_operator_action (
                id VARCHAR(64) NOT NULL PRIMARY KEY,
                message_id VARCHAR(64) NOT NULL,
                operator_identifier VARCHAR(255) NOT NULL,
                operator_display_name VARCHAR(255) DEFAULT NULL,
                action VARCHAR(64) NOT NULL,
                created_at DATETIME NOT NULL
            )'
        );
    }

    protected function createMessageRepository(): MessageRepository
    {
        $presentationFormatter = new MessagePresentationFormatter();

        return new MessageRepository(
            $this->connection,
            new OperatorActionPolicy(),
            $presentationFormatter,
            new MessageWhereClauseBuilder(),
            new MessageRowGrouper($presentationFormatter)
        );
    }

    protected function createMessageAuditWriter(): MessageAuditWriter
    {
        return new MessageAuditWriter(
            $this->createMessageRepository(),
            new TransitionRepository($this->connection),
            new FailureRepository($this->connection),
            new PayloadSerializer()
        );
    }

    protected function insertMessage(
        string $id,
        string $messageClass,
        string $source,
        string $status,
        string $createdAt
    ): void {
        $this->connection->insert('mh_message', [
            'id' => $id,
            'message_class' => $messageClass,
            'source' => $source,
            'subject_type' => null,
            'subject_id' => null,
            'correlation_id' => null,
            'causation_id' => null,
            'transport_name' => null,
            'business_reference' => null,
            'payload_json' => null,
            'status' => $status,
            'retry_count' => 0,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    protected function messageExists(string $id): bool
    {
        return $this->connection->fetchOne(
            'SELECT COUNT(*) FROM mh_message WHERE id = :id',
            ['id' => $id]
        ) === 1;
    }
}
