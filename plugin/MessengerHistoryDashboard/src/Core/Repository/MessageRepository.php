<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use MessengerHistoryDashboard\Core\Operator\OperatorActionPolicy;

final class MessageRepository
{
    private const DATETIME_FORMAT = 'Y-m-d H:i:s';
    private ?bool $auditSchemaAvailable = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly OperatorActionPolicy $operatorActionPolicy,
        private readonly MessagePresentationFormatter $presentationFormatter,
        private readonly MessageWhereClauseBuilder $whereClauseBuilder,
        private readonly MessageRowGrouper $rowGrouper
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

        if (! $this->hasAuditSchema()) {
            return [
                'data' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
            ];
        }

        $offset = ($page - 1) * $limit;

        $baseSql = <<<'SQL'
FROM mh_message m
SQL;

        $where = $this->whereClauseBuilder->buildWhereClause($criteria);
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
        $groupedRows = $this->rowGrouper->groupRows($this->mapRows($rows, $locale), $locale);
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
        if (! $this->hasAuditSchema()) {
            return false;
        }

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
        if (! $this->hasAuditSchema()) {
            return [];
        }

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

    /**
     * @return list<array<string, array<array-key, scalar|null>|list<string>|scalar|null>>
     */
    public function findRelatedMessengerLifecycle(string $messageClass, \DateTimeImmutable $createdAt, string $locale = 'de-DE'): array
    {
        if (! $this->hasAuditSchema()) {
            return [];
        }

        $bucketStart = $createdAt->setTime(
            (int) $createdAt->format('H'),
            (int) $createdAt->format('i'),
            0
        );
        $bucketEnd = $bucketStart->add(new \DateInterval('PT1M'));

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, message_class, source, subject_type, subject_id, correlation_id, causation_id, transport_name, business_reference, payload_json, status, retry_count, created_at, updated_at
             FROM mh_message
             WHERE source = :source
               AND message_class = :messageClass
               AND created_at >= :bucketStart
               AND created_at < :bucketEnd
               AND (subject_type IS NULL OR subject_type = \'\')
               AND (business_reference IS NULL OR business_reference = \'\')
             ORDER BY created_at ASC',
            [
                'source' => 'messenger',
                'messageClass' => $messageClass,
                'bucketStart' => $bucketStart->format(self::DATETIME_FORMAT),
                'bucketEnd' => $bucketEnd->format(self::DATETIME_FORMAT),
            ]
        );

        return $this->mapRows($rows, $locale);
    }

    public function insertIfMissing(string $id, string $class, string $payloadJson, string $status, MessageMetadata $metadata): void
    {
        if (! $this->hasAuditSchema()) {
            return;
        }

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
        if (! $this->hasAuditSchema()) {
            return;
        }

        $this->connection->update('mh_message', [
            'status' => $status,
            'updated_at' => (new \DateTimeImmutable())->format(self::DATETIME_FORMAT),
        ], ['id' => $id]);
    }

    public function incrementRetryCount(string $id): void
    {
        if (! $this->hasAuditSchema()) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE mh_message SET retry_count = retry_count + 1, updated_at = :updatedAt WHERE id = :id',
            ['id' => $id, 'updatedAt' => (new \DateTimeImmutable())->format(self::DATETIME_FORMAT)]
        );
    }

    public function syncRetryCount(string $id, int $retryCount): void
    {
        if (! $this->hasAuditSchema()) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE mh_message
             SET retry_count = CASE WHEN retry_count > :retryCount THEN retry_count ELSE :retryCount END,
                 updated_at = :updatedAt
             WHERE id = :id',
            [
                'id' => $id,
                'retryCount' => $retryCount,
                'updatedAt' => (new \DateTimeImmutable())->format(self::DATETIME_FORMAT),
            ]
        );
    }

    public function updateMetadata(string $id, MessageMetadata $metadata): void
    {
        if (! $this->hasAuditSchema()) {
            return;
        }

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
     * @return array{messenger_24h:int, retries:int, failures:int, order_payment_states:int}
     */
    public function metrics(): array
    {
        if (! $this->hasAuditSchema()) {
            return [
                'messenger_24h' => 0,
                'retries' => 0,
                'failures' => 0,
                'order_payment_states' => 0,
            ];
        }

        $messengerCutoff = $this->whereClauseBuilder->createUtc24HourCutoff();

        return [
            'messenger_24h' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM mh_message
                 WHERE source = :source
                   AND SUBSTR(message_class, 1, :shopwarePrefixLength) = :shopwarePrefix
                   AND created_at >= :cutoff',
                [
                    'source' => 'messenger',
                    'shopwarePrefix' => MessageWhereClauseBuilder::SHOPWARE_CLASS_PREFIX,
                    'shopwarePrefixLength' => \strlen(MessageWhereClauseBuilder::SHOPWARE_CLASS_PREFIX),
                    'cutoff' => $messengerCutoff,
                ]
            ),
            'retries' => (int) $this->connection->fetchOne(
                'SELECT COALESCE(SUM(retry_count), 0) FROM mh_message
                 WHERE source = :source
                   AND created_at >= :cutoff',
                [
                    'source' => 'messenger',
                    'cutoff' => $messengerCutoff,
                ]
            ),
            'failures' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM mh_message
                 WHERE (source = :messengerSource AND status = :failedStatus)
                    OR (
                        source = :stateChangeSource
                        AND subject_type IN (\'order\', \'order_transaction\')
                        AND status IN (\'failed\', \'cancelled\')
                    )',
                [
                    'messengerSource' => 'messenger',
                    'failedStatus' => 'failed',
                    'stateChangeSource' => 'state_change',
                ]
            ),
            'order_payment_states' => (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM mh_message
                 WHERE source = :source
                   AND subject_type IN (\'order\', \'order_transaction\')',
                ['source' => 'state_change']
            ),
        ];
    }

    public function cleanupShopwareMessengerEntriesOlderThan24Hours(): int
    {
        if (! $this->hasAuditSchema()) {
            return 0;
        }

        $messengerCutoff = $this->whereClauseBuilder->createUtc24HourCutoff();

        $messageIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM mh_message
             WHERE source = :source
               AND SUBSTR(message_class, 1, :shopwarePrefixLength) = :shopwarePrefix
               AND created_at < :cutoff',
            [
                'source' => 'messenger',
                'shopwarePrefix' => MessageWhereClauseBuilder::SHOPWARE_CLASS_PREFIX,
                'shopwarePrefixLength' => \strlen(MessageWhereClauseBuilder::SHOPWARE_CLASS_PREFIX),
                'cutoff' => $messengerCutoff,
            ]
        );

        if ($messageIds === []) {
            return 0;
        }

        $ids = array_values(array_filter($messageIds, 'is_string'));

        if ($ids === []) {
            return 0;
        }

        $this->connection->executeStatement(
            'DELETE FROM mh_transition WHERE message_id IN (:ids)',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::STRING]
        );

        $this->connection->executeStatement(
            'DELETE FROM mh_failure WHERE message_id IN (:ids)',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::STRING]
        );

        $this->connection->executeStatement(
            'DELETE FROM mh_operator_action WHERE message_id IN (:ids)',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::STRING]
        );

        return $this->connection->executeStatement(
            'DELETE FROM mh_message WHERE id IN (:ids)',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::STRING]
        );
    }

    /**
     * @return array{messages:int, transitions:int, failures:int, actions:int}
     */
    public function cleanupOlderThan(\DateTimeImmutable $cutoff): array
    {
        if (! $this->hasAuditSchema()) {
            return [
                'messages' => 0,
                'transitions' => 0,
                'failures' => 0,
                'actions' => 0,
            ];
        }

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

    public function hasAuditSchema(): bool
    {
        if ($this->auditSchemaAvailable !== null) {
            return $this->auditSchemaAvailable;
        }

        return $this->auditSchemaAvailable = $this->connection
            ->createSchemaManager()
            ->tablesExist(['mh_message', 'mh_transition', 'mh_failure', 'mh_operator_action']);
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
}
