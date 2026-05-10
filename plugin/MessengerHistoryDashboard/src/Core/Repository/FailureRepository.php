<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Repository;

use Doctrine\DBAL\Connection;

final class FailureRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function insert(string $messageId, string $exceptionClass, string $errorMessage): void
    {
        $this->connection->insert('mh_failure', [
            'id' => bin2hex(random_bytes(16)),
            'message_id' => $messageId,
            'exception_class' => $exceptionClass,
            'error_message' => $errorMessage,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByMessageId(string $messageId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT exception_class, error_message, created_at FROM mh_failure WHERE message_id = :id ORDER BY created_at DESC',
            ['id' => $messageId]
        );
    }
}
