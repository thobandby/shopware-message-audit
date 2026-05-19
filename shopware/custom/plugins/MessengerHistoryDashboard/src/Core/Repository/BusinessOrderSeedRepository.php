<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class BusinessOrderSeedRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function findLatestAutoIncrement(): int
    {
        $value = $this->connection->fetchOne('SELECT COALESCE(MAX(auto_increment), 0) FROM `order`');

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @return list<array{id: string, order_number: string, transaction_id: string|null, delivery_id: string|null}>
     */
    public function findCreatedAfterAutoIncrement(int $lastAutoIncrement, int $limit): array
    {
        return array_map(
            static fn (array $order): array => [
                'id' => (string) $order['id'],
                'order_number' => (string) $order['order_number'],
                'transaction_id' => \is_string($order['transaction_id'] ?? null) && $order['transaction_id'] !== ''
                    ? strtolower($order['transaction_id'])
                    : null,
                'delivery_id' => \is_string($order['delivery_id'] ?? null) && $order['delivery_id'] !== ''
                    ? strtolower($order['delivery_id'])
                    : null,
            ],
            $this->connection->fetchAllAssociative(
                'SELECT
                LOWER(HEX(o.id)) AS id,
                o.order_number,
                LOWER(HEX(o.primary_order_transaction_id)) AS transaction_id,
                LOWER(HEX(o.primary_order_delivery_id)) AS delivery_id
            FROM `order` o
                WHERE o.auto_increment > :lastAutoIncrement
                ORDER BY o.auto_increment ASC
                LIMIT :limit',
                [
                    'lastAutoIncrement' => $lastAutoIncrement,
                    'limit' => $limit,
                ],
                [
                    'lastAutoIncrement' => ParameterType::INTEGER,
                    'limit' => ParameterType::INTEGER,
                ]
            )
        );
    }
}
