<?php declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Repository;

use Doctrine\DBAL\Connection;

final class TransitionRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function insert(string $messageId, string $event): void
    {
        $this->connection->insert('mh_transition', [
            'id' => bin2hex(random_bytes(16)),
            'message_id' => $messageId,
            'event' => $event,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByMessageId(string $messageId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT event, created_at FROM mh_transition WHERE message_id = :id ORDER BY created_at ASC',
            ['id' => $messageId]
        );
    }
}
