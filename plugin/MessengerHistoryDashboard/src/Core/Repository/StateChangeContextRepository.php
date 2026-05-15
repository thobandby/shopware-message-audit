<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Repository;

use Doctrine\DBAL\Connection;

final class StateChangeContextRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array{orderId: ?string, businessReference: ?string}
     */
    public function findBySubject(string $subjectType, string $subjectId): array
    {
        $context = $this->connection->fetchAssociative(
            match ($subjectType) {
                'order' => 'SELECT LOWER(HEX(o.id)) AS orderId, o.order_number AS businessReference
                    FROM `order` o
                    WHERE o.id = UNHEX(:id)',
                'order_transaction' => 'SELECT LOWER(HEX(ot.order_id)) AS orderId, o.order_number AS businessReference
                    FROM order_transaction ot
                    INNER JOIN `order` o ON o.id = ot.order_id
                    WHERE ot.id = UNHEX(:id)',
                'order_delivery' => 'SELECT LOWER(HEX(od.order_id)) AS orderId, o.order_number AS businessReference
                    FROM order_delivery od
                    INNER JOIN `order` o ON o.id = od.order_id
                    WHERE od.id = UNHEX(:id)',
                default => 'SELECT NULL AS orderId, NULL AS businessReference',
            },
            ['id' => $subjectId]
        );

        if ($context === false) {
            return [
                'orderId' => null,
                'businessReference' => null,
            ];
        }

        return [
            'orderId' => \is_string($context['orderId'] ?? null) && $context['orderId'] !== ''
                ? strtolower($context['orderId'])
                : null,
            'businessReference' => \is_string($context['businessReference'] ?? null) && $context['businessReference'] !== ''
                ? $context['businessReference']
                : null,
        ];
    }
}
