<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Repository;

use Doctrine\DBAL\Connection;
use MessengerHistoryDashboard\Core\Operator\OperatorActionPolicy;

final class MessageRepository
{
    private const DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly Connection $connection,
        private readonly OperatorActionPolicy $operatorActionPolicy,
        private readonly MessagePresentationFormatter $presentationFormatter
    ) {
    }

    /**
     * @return array{
     *     data:list<array<string, array<array-key, scalar|null>|list<string>|scalar|null>>,
     *     total:int,
     *     page:int,
     *     limit:int
     * }
     */
    public function list(MessageListCriteria $criteria, string $locale = 'de-DE'): array
    {
        $page = max(1, $criteria->page);
        $limit = max(1, min(100, $criteria->limit));
        $offset = ($page - 1) * $limit;

        $baseSql = <<<'SQL'
FROM mh_message m
SQL;

        $where = $this->buildWhereClause(
            $criteria
        );
        $parameters = $where['parameters'];

        $selectSql = <<<'SQL'
SELECT
    m.id,
    m.message_class,
    m.source,
    m.subject_type,
    m.subject_id,
    m.correlation_id,
    m.causation_id,
    m.transport_name,
    m.business_reference,
    SUBSTRING_INDEX(m.message_class, '\\', -1) AS message_name,
    CASE
        WHEN m.message_class LIKE '%\\Checkout\\Order\\%' THEN 'Bestellung'
        WHEN m.message_class LIKE '%\\Checkout\\Payment\\%' THEN 'Zahlung'
        WHEN m.message_class LIKE '%\\DataAbstractionLayer\\Indexing\\%' THEN 'Indexer'
        WHEN m.message_class LIKE '%\\Content\\Flow\\%' THEN 'Ablauf'
        WHEN m.message_class LIKE '%\\Content\\Mail\\%' THEN 'E-Mail'
        WHEN m.message_class LIKE '%\\Framework\\Webhook\\%' THEN 'Webhook'
        WHEN m.message_class LIKE '%\\Content\\Media\\%' THEN 'Medien'
        WHEN m.message_class LIKE 'MessengerHistoryDashboard\\%' THEN 'Plugin'
        WHEN m.message_class LIKE 'Swag\\PayPal\\%' THEN 'PayPal'
        ELSE 'Sonstiges'
    END AS message_type,
    CASE
        WHEN m.message_class LIKE '%\\Checkout\\Order\\%' THEN 'Bestellungen'
        WHEN m.message_class LIKE '%\\Checkout\\Payment\\%' THEN 'Zahlungen'
        WHEN m.message_class LIKE '%\\Content\\Mail\\%' THEN 'Kommunikation'
        WHEN m.message_class LIKE '%\\Framework\\Webhook\\%' THEN 'Integrationen'
        WHEN m.message_class LIKE 'Swag\\PayPal\\%' THEN 'Integrationen'
        WHEN m.message_class LIKE '%\\Content\\Media\\%' THEN 'Inhalte'
        WHEN m.message_class LIKE '%\\Content\\Category\\%' THEN 'Inhalte'
        WHEN m.message_class LIKE '%\\Content\\Rule\\%' THEN 'Inhalte'
        ELSE 'System'
    END AS topic_group,
    m.status,
    m.retry_count,
    m.created_at,
    m.updated_at,
    (
        SELECT oa.action
        FROM mh_operator_action oa
        WHERE oa.message_id = m.id
        ORDER BY oa.created_at DESC
        LIMIT 1
    ) AS latest_action
SQL;

        $listSql = $selectSql . ' ' . $baseSql . $where['sql'] . ' ORDER BY m.created_at DESC';
        $rows = $this->connection->fetchAllAssociative($listSql, $parameters);
        $groupedRows = $this->groupRows($this->mapRows($rows, $locale), $locale);
        $total = \count($groupedRows);

        return [
            'data' => \array_slice($groupedRows, $offset, $limit),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public function find(string $id, string $locale = 'de-DE'): array|false
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, message_class, source, subject_type, subject_id, correlation_id, causation_id, transport_name, business_reference, payload_json, status, retry_count, created_at, updated_at
             FROM mh_message
             WHERE id = :id',
            ['id' => $id]
        );

        if ($row === false) {
            return false;
        }

        return $this->mapRow($row, $locale);
    }

    /**
     * @return list<array<string, array<array-key, scalar|null>|list<string>|scalar|null>>
     */
    public function findRelatedStateChangesByBusinessReference(string $businessReference, string $locale = 'de-DE'): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, message_class, source, subject_type, subject_id, correlation_id, causation_id, transport_name, business_reference, payload_json, status, retry_count, created_at, updated_at
             FROM mh_message
             WHERE source = :source AND business_reference = :businessReference
             ORDER BY created_at ASC',
            [
                'source' => 'state_change',
                'businessReference' => $businessReference,
            ]
        );

        return $this->mapRows($rows, $locale);
    }

    public function insertIfMissing(string $id, string $class, string $payloadJson, string $status, MessageMetadata $metadata): void
    {
        if ($this->connection->fetchOne('SELECT id FROM mh_message WHERE id = :id', ['id' => $id]) !== false) {
            return;
        }

        $now = (new \DateTimeImmutable())->format(self::DATETIME_FORMAT);
        $this->connection->insert('mh_message', [
            'id' => $id,
            'message_class' => $class,
            'source' => $metadata->source,
            'subject_type' => $metadata->subjectType,
            'subject_id' => $metadata->subjectId,
            'correlation_id' => $metadata->correlationId,
            'causation_id' => $metadata->causationId,
            'transport_name' => $metadata->transportName,
            'business_reference' => $metadata->businessReference,
            'payload_json' => $payloadJson,
            'status' => $status,
            'retry_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function updateStatus(string $id, string $status): void
    {
        $this->connection->update('mh_message', [
            'status' => $status,
            'updated_at' => (new \DateTimeImmutable())->format(self::DATETIME_FORMAT),
        ], ['id' => $id]);
    }

    public function incrementRetryCount(string $id): void
    {
        $this->connection->executeStatement(
            'UPDATE mh_message SET retry_count = retry_count + 1, updated_at = :updatedAt WHERE id = :id',
            ['id' => $id, 'updatedAt' => (new \DateTimeImmutable())->format(self::DATETIME_FORMAT)]
        );
    }

    public function updateMetadata(string $id, MessageMetadata $metadata): void
    {
        $updates = ['updated_at' => (new \DateTimeImmutable())->format(self::DATETIME_FORMAT)];

        if ($metadata->correlationId !== null && $metadata->correlationId !== '') {
            $updates['correlation_id'] = $metadata->correlationId;
        }

        if ($metadata->causationId !== null && $metadata->causationId !== '') {
            $updates['causation_id'] = $metadata->causationId;
        }

        if ($metadata->transportName !== null && $metadata->transportName !== '') {
            $updates['transport_name'] = $metadata->transportName;
        }

        if ($metadata->businessReference !== null && $metadata->businessReference !== '') {
            $updates['business_reference'] = $metadata->businessReference;
        }

        if ($metadata->source !== '') {
            $updates['source'] = $metadata->source;
        }

        if ($metadata->subjectType !== null && $metadata->subjectType !== '') {
            $updates['subject_type'] = $metadata->subjectType;
        }

        if ($metadata->subjectId !== null && $metadata->subjectId !== '') {
            $updates['subject_id'] = $metadata->subjectId;
        }

        if (\count($updates) === 1) {
            return;
        }

        $this->connection->update('mh_message', $updates, ['id' => $id]);
    }

    /**
     * @return array{total:int, messenger_failed:int, state_changes:int, orders:int, payments:int}
     */
    public function metrics(): array
    {
        return [
            'total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mh_message'),
            'messenger_failed' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM mh_message WHERE source = :source AND status = :status',
                ['source' => 'messenger', 'status' => 'failed']
            ),
            'state_changes' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM mh_message WHERE source = :source',
                ['source' => 'state_change']
            ),
            'orders' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM mh_message WHERE subject_type = :subjectType',
                ['subjectType' => 'order']
            ),
            'payments' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM mh_message WHERE subject_type = :subjectType',
                ['subjectType' => 'order_transaction']
            ),
        ];
    }

    /**
     * @return array{messages:int, transitions:int, failures:int, actions:int}
     */
    public function cleanupOlderThan(\DateTimeImmutable $cutoff): array
    {
        $formattedCutoff = $cutoff->format(self::DATETIME_FORMAT);

        $messages = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM mh_message WHERE created_at < :cutoff',
            ['cutoff' => $formattedCutoff]
        );

        $transitions = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM mh_transition WHERE message_id IN (
                SELECT id FROM (SELECT id FROM mh_message WHERE created_at < :cutoff) old_messages
            )',
            ['cutoff' => $formattedCutoff]
        );

        $failures = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM mh_failure WHERE message_id IN (
                SELECT id FROM (SELECT id FROM mh_message WHERE created_at < :cutoff) old_messages
            )',
            ['cutoff' => $formattedCutoff]
        );

        $actions = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM mh_operator_action WHERE message_id IN (
                SELECT id FROM (SELECT id FROM mh_message WHERE created_at < :cutoff) old_messages
            )',
            ['cutoff' => $formattedCutoff]
        );

        $this->connection->executeStatement(
            'DELETE FROM mh_transition WHERE message_id IN (
                SELECT id FROM (SELECT id FROM mh_message WHERE created_at < :cutoff) old_messages
            )',
            ['cutoff' => $formattedCutoff]
        );

        $this->connection->executeStatement(
            'DELETE FROM mh_failure WHERE message_id IN (
                SELECT id FROM (SELECT id FROM mh_message WHERE created_at < :cutoff) old_messages
            )',
            ['cutoff' => $formattedCutoff]
        );

        $this->connection->executeStatement(
            'DELETE FROM mh_operator_action WHERE message_id IN (
                SELECT id FROM (SELECT id FROM mh_message WHERE created_at < :cutoff) old_messages
            )',
            ['cutoff' => $formattedCutoff]
        );

        $this->connection->executeStatement(
            'DELETE FROM mh_message WHERE created_at < :cutoff',
            ['cutoff' => $formattedCutoff]
        );

        return [
            'messages' => $messages,
            'transitions' => $transitions,
            'failures' => $failures,
            'actions' => $actions,
        ];
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     *
     * @return list<array<string, array<array-key, scalar|null>|list<string>|scalar|null>>
     */
    private function mapRows(array $rows, string $locale): array
    {
        return array_map(fn (array $row): array => $this->mapRow($row, $locale), $rows);
    }

    /**
     * @param array<string, scalar|null> $row
     *
     * @return array<string, array<array-key, scalar|null>|list<string>|scalar|null>
     */
    private function mapRow(array $row, string $locale): array
    {
        $source = (string) ($row['source'] ?? 'messenger');
        $status = (string) ($row['status'] ?? '');
        $allowedActions = $source === 'messenger' ? $this->operatorActionPolicy->allowedActions($status) : [];
        $availableActions = $source === 'messenger' ? $this->operatorActionPolicy->formatAllowedActions($status) : '';

        return $this->presentationFormatter->formatRow($row, $allowedActions, $availableActions, $locale);
    }

    /**
     * @param list<array<string, array<array-key, scalar|null>|list<string>|scalar|null>> $rows
     *
     * @return list<array<string, array<array-key, scalar|null>|list<string>|scalar|null>>
     */
    private function groupRows(array $rows, string $locale): array
    {
        $groupedRows = [];

        foreach ($rows as $row) {
            $source = (string) ($row['source'] ?? '');

            if ($source !== 'state_change') {
                $groupedRows[] = $row;

                continue;
            }

            $businessReference = (string) ($row['business_reference'] ?? '');
            $subjectType = (string) ($row['subject_type'] ?? '');
            $subjectId = (string) ($row['subject_id'] ?? '');
            $groupKey = $businessReference !== ''
                ? 'state_change:' . strtolower($businessReference)
                : 'state_change:' . $subjectType . ':' . $subjectId;

            if (! isset($groupedRows[$groupKey])) {
                $row['group_count'] = 1;
                $groupedRows[$groupKey] = $this->presentationFormatter->formatGroupedStateChangeRow($row, $locale);

                continue;
            }

            $groupedRows[$groupKey]['group_count'] = (int) ($groupedRows[$groupKey]['group_count'] ?? 1) + 1;

            if ($this->presentationFormatter->shouldReplaceGroupedStateChange($groupedRows[$groupKey], $row)) {
                $row['group_count'] = (int) $groupedRows[$groupKey]['group_count'];
                $groupedRows[$groupKey] = $this->presentationFormatter->formatGroupedStateChangeRow($row, $locale);
            }
        }

        return array_values($groupedRows);
    }

    /**
     * @return array{sql:string, parameters:array<string, int|string>}
     */
    private function buildWhereClause(MessageListCriteria $criteria): array
    {
        $conditions = [];
        $parameters = [];

        if ($criteria->status !== null) {
            $conditions[] = 'm.status = :status';
            $parameters['status'] = $criteria->status;
        }

        if ($criteria->createdFrom !== null) {
            $conditions[] = 'm.created_at >= :createdFrom';
            $parameters['createdFrom'] = $criteria->createdFrom->format(self::DATETIME_FORMAT);
        }

        if ($criteria->createdTo !== null) {
            $conditions[] = 'm.created_at <= :createdTo';
            $parameters['createdTo'] = $criteria->createdTo->format(self::DATETIME_FORMAT);
        }

        if ($criteria->query !== null && $criteria->query !== '') {
            $conditions[] = '(m.id LIKE :query OR m.message_class LIKE :query OR m.business_reference LIKE :query OR m.subject_type LIKE :query)';
            $parameters['query'] = '%' . $criteria->query . '%';
        }

        if ($criteria->messageClass !== null && $criteria->messageClass !== '') {
            $conditions[] = 'm.message_class LIKE :messageClass';
            $parameters['messageClass'] = '%' . $criteria->messageClass . '%';
        }

        if ($criteria->transportName !== null && $criteria->transportName !== '') {
            $conditions[] = 'm.transport_name LIKE :transportName';
            $parameters['transportName'] = '%' . $criteria->transportName . '%';
        }

        if ($criteria->businessReference !== null && $criteria->businessReference !== '') {
            $conditions[] = 'm.business_reference LIKE :businessReference';
            $parameters['businessReference'] = '%' . $criteria->businessReference . '%';
        }

        if ($criteria->topicGroup !== null && $criteria->topicGroup !== '') {
            $conditions[] = match ($criteria->topicGroup) {
                'Bestellungen' => "(m.subject_type IN ('order', 'order_delivery') OR m.message_class LIKE '%\\\\Checkout\\\\Order\\\\%')",
                'Zahlungen' => "(m.subject_type = 'order_transaction' OR m.message_class LIKE '%\\\\Checkout\\\\Payment\\\\%' OR m.message_class LIKE 'Swag\\\\PayPal\\\\%')",
                'Kommunikation' => "m.message_class LIKE '%\\\\Content\\\\Mail\\\\%'",
                'Integrationen' => "(m.message_class LIKE '%\\\\Framework\\\\Webhook\\\\%' OR m.message_class LIKE 'Swag\\\\PayPal\\\\%')",
                'Inhalte' => "(m.message_class LIKE '%\\\\Content\\\\Media\\\\%' OR m.message_class LIKE '%\\\\Content\\\\Category\\\\%' OR m.message_class LIKE '%\\\\Content\\\\Rule\\\\%')",
                'System' => "(m.message_class NOT LIKE '%\\\\Checkout\\\\Order\\\\%' AND m.message_class NOT LIKE '%\\\\Checkout\\\\Payment\\\\%' AND m.message_class NOT LIKE '%\\\\Content\\\\Mail\\\\%' AND m.message_class NOT LIKE '%\\\\Framework\\\\Webhook\\\\%' AND m.message_class NOT LIKE 'Swag\\\\PayPal\\\\%' AND m.message_class NOT LIKE '%\\\\Content\\\\Media\\\\%' AND m.message_class NOT LIKE '%\\\\Content\\\\Category\\\\%' AND m.message_class NOT LIKE '%\\\\Content\\\\Rule\\\\%')",
                default => '1 = 1',
            };
        }

        if ($conditions === []) {
            return ['sql' => '', 'parameters' => []];
        }

        return [
            'sql' => ' WHERE ' . implode(' AND ', $conditions),
            'parameters' => $parameters,
        ];
    }
}
