<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Repository;

final class MessageWhereClauseBuilder
{
    public const DATETIME_FORMAT = 'Y-m-d H:i:s';
    public const SHOPWARE_CLASS_PREFIX = 'Shopware\\';

    public function createUtc24HourCutoff(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->sub(new \DateInterval('P1D'))
            ->format(self::DATETIME_FORMAT);
    }

    /**
     * @return array{sql:string, parameters:array<string, int|string>}
     */
    public function buildWhereClause(MessageListCriteria $criteria): array
    {
        $conditions = [];
        $parameters = [];

        $this->addStatusCondition($conditions, $parameters, $criteria);
        $this->addCreatedFromCondition($conditions, $parameters, $criteria);
        $this->addCreatedToCondition($conditions, $parameters, $criteria);
        $this->addLikeCondition($conditions, $parameters, 'query', $criteria->query, '(m.id LIKE :query OR m.message_class LIKE :query OR m.business_reference LIKE :query OR m.subject_type LIKE :query)');
        $this->addLikeCondition($conditions, $parameters, 'messageClass', $criteria->messageClass, 'm.message_class LIKE :messageClass');
        $this->addLikeCondition($conditions, $parameters, 'transportName', $criteria->transportName, 'm.transport_name LIKE :transportName');
        $this->addLikeCondition($conditions, $parameters, 'businessReference', $criteria->businessReference, 'm.business_reference LIKE :businessReference');
        $this->addEntryFilterCondition($conditions, $parameters, $criteria);
        $this->addTopicGroupCondition($conditions, $criteria);

        if ($conditions === []) {
            return ['sql' => '', 'parameters' => []];
        }

        return [
            'sql' => ' WHERE ' . implode(' AND ', $conditions),
            'parameters' => $parameters,
        ];
    }

    /**
     * @param list<string> $conditions
     * @param array<string, int|string> $parameters
     */
    private function addStatusCondition(array &$conditions, array &$parameters, MessageListCriteria $criteria): void
    {
        if ($criteria->status === null) {
            return;
        }

        $conditions[] = $criteria->status === 'failed'
            ? "(m.status = :status OR (m.source = 'state_change' AND m.subject_type IN ('order', 'order_transaction') AND m.status IN ('failed', 'cancelled')))"
            : 'm.status = :status';
        $parameters['status'] = $criteria->status;
    }

    /**
     * @param list<string> $conditions
     * @param array<string, int|string> $parameters
     */
    private function addCreatedFromCondition(array &$conditions, array &$parameters, MessageListCriteria $criteria): void
    {
        if ($criteria->createdFrom === null) {
            return;
        }

        $conditions[] = 'm.created_at >= :createdFrom';
        $parameters['createdFrom'] = $criteria->createdFrom->format(self::DATETIME_FORMAT);
    }

    /**
     * @param list<string> $conditions
     * @param array<string, int|string> $parameters
     */
    private function addCreatedToCondition(array &$conditions, array &$parameters, MessageListCriteria $criteria): void
    {
        if ($criteria->createdTo === null) {
            return;
        }

        $conditions[] = 'm.created_at <= :createdTo';
        $parameters['createdTo'] = $criteria->createdTo->format(self::DATETIME_FORMAT);
    }

    /**
     * @param list<string> $conditions
     * @param array<string, int|string> $parameters
     */
    private function addLikeCondition(array &$conditions, array &$parameters, string $parameterName, ?string $value, string $sql): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $conditions[] = $sql;
        $parameters[$parameterName] = '%' . $value . '%';
    }

    /**
     * @param list<string> $conditions
     * @param array<string, int|string> $parameters
     */
    private function addEntryFilterCondition(array &$conditions, array &$parameters, MessageListCriteria $criteria): void
    {
        if ($criteria->entryFilter === null || $criteria->entryFilter === '') {
            return;
        }

        $condition = $this->resolveEntryFilterCondition($criteria->entryFilter);

        if ($condition === null) {
            return;
        }

        $conditions[] = $condition;

        if (\in_array($criteria->entryFilter, ['shopware_messenger', 'internal_jobs'], true)) {
            $parameters['entryFilterShopwarePrefix'] = self::SHOPWARE_CLASS_PREFIX;
            $parameters['entryFilterShopwarePrefixLength'] = \strlen(self::SHOPWARE_CLASS_PREFIX);
        }

        if ($criteria->entryFilter === 'shopware_messenger') {
            $parameters['entryFilterCutoff'] = $this->createUtc24HourCutoff();
        }
    }

    private function resolveEntryFilterCondition(string $entryFilter): ?string
    {
        return match ($entryFilter) {
            'shopware_messenger' => "(m.source = 'messenger' AND SUBSTR(m.message_class, 1, :entryFilterShopwarePrefixLength) = :entryFilterShopwarePrefix AND m.created_at >= :entryFilterCutoff)",
            'internal_jobs' => "(m.source = 'messenger' AND SUBSTR(m.message_class, 1, :entryFilterShopwarePrefixLength) <> :entryFilterShopwarePrefix)",
            'orders' => "(m.subject_type IN ('order', 'order_delivery') OR m.message_class LIKE '%\\\\Checkout\\\\Order\\\\%')",
            'payments' => "(m.subject_type = 'order_transaction' OR m.message_class LIKE '%\\\\Checkout\\\\Payment\\\\%' OR m.message_class LIKE 'Swag\\\\PayPal\\\\%')",
            default => null,
        };
    }

    /**
     * @param list<string> $conditions
     */
    private function addTopicGroupCondition(array &$conditions, MessageListCriteria $criteria): void
    {
        if ($criteria->topicGroup === null || $criteria->topicGroup === '') {
            return;
        }

        $condition = $this->resolveTopicGroupCondition($criteria->topicGroup);

        if ($condition === null) {
            return;
        }

        $conditions[] = $condition;
    }

    private function resolveTopicGroupCondition(string $topicGroup): ?string
    {
        return match ($topicGroup) {
            'Bestellungen' => "(m.subject_type IN ('order', 'order_delivery') OR m.message_class LIKE '%\\\\Checkout\\\\Order\\\\%')",
            'Zahlungen' => "(m.subject_type = 'order_transaction' OR m.message_class LIKE '%\\\\Checkout\\\\Payment\\\\%' OR m.message_class LIKE 'Swag\\\\PayPal\\\\%')",
            'Kommunikation' => "m.message_class LIKE '%\\\\Content\\\\Mail\\\\%'",
            'Integrationen' => "(m.message_class LIKE '%\\\\Framework\\\\Webhook\\\\%' OR m.message_class LIKE 'Swag\\\\PayPal\\\\%')",
            'Inhalte' => "(m.message_class LIKE '%\\\\Content\\\\Media\\\\%' OR m.message_class LIKE '%\\\\Content\\\\Category\\\\%' OR m.message_class LIKE '%\\\\Content\\\\Rule\\\\%')",
            'System' => "(m.message_class NOT LIKE '%\\\\Checkout\\\\Order\\\\%' AND m.message_class NOT LIKE '%\\\\Checkout\\\\Payment\\\\%' AND m.message_class NOT LIKE '%\\\\Content\\\\Mail\\\\%' AND m.message_class NOT LIKE '%\\\\Framework\\\\Webhook\\\\%' AND m.message_class NOT LIKE 'Swag\\\\PayPal\\\\%' AND m.message_class NOT LIKE '%\\\\Content\\\\Media\\\\%' AND m.message_class NOT LIKE '%\\\\Content\\\\Category\\\\%' AND m.message_class NOT LIKE '%\\\\Content\\\\Rule\\\\%')",
            default => null,
        };
    }
}
