<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Repository;

use Doctrine\DBAL\Connection;
use MessengerHistoryDashboard\Core\Operator\OperatorIdentity;

final class OperatorActionRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function insert(string $messageId, string $action, string $reason, ?OperatorIdentity $operator = null): void
    {
        $this->connection->insert('mh_operator_action', [
            'id' => bin2hex(random_bytes(16)),
            'message_id' => $messageId,
            'action' => $action,
            'reason' => $reason,
            'operator_id' => $operator?->getOperatorId(),
            'operator_type' => $operator?->getOperatorType(),
            'operator_label' => $operator?->getOperatorLabel(),
            'operator_email' => $operator?->getOperatorEmail(),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByMessageId(string $messageId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT action, reason, operator_id, operator_type, operator_label, operator_email, created_at
             FROM mh_operator_action
             WHERE message_id = :id
             ORDER BY created_at DESC',
            ['id' => $messageId]
        );
    }
}
