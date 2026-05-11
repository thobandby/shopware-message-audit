<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use MessengerHistoryDashboard\Core\Operator\OperatorActionPolicy;

final class MessageRepository
{
    private const DATETIME_FORMAT = 'Y-m-d H:i:s';
    private const REVIEW_LABEL = 'Prüfen';

    public function __construct(
        private readonly Connection $connection,
        private readonly OperatorActionPolicy $operatorActionPolicy
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
    public function list(MessageListCriteria $criteria): array
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

        $countSql = 'SELECT COUNT(*) ' . $baseSql . $where['sql'];
        $total = (int) $this->connection->fetchOne($countSql, $parameters);

        $listSql = $selectSql . ' ' . $baseSql . $where['sql'] . ' ORDER BY m.created_at DESC LIMIT :limit OFFSET :offset';
        $rows = $this->connection->fetchAllAssociative(
            $listSql,
            [
                ...$parameters,
                'limit' => $limit,
                'offset' => $offset,
            ],
            [
                'limit' => ParameterType::INTEGER,
                'offset' => ParameterType::INTEGER,
            ]
        );

        return [
            'data' => $this->mapRows($rows),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public function find(string $id): array|false
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, message_class, correlation_id, causation_id, transport_name, business_reference, payload_json, status, retry_count, created_at, updated_at
             FROM mh_message
             WHERE id = :id',
            ['id' => $id]
        );

        if ($row === false) {
            return false;
        }

        return $this->mapRow($row);
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

        if (\count($updates) === 1) {
            return;
        }

        $this->connection->update('mh_message', $updates, ['id' => $id]);
    }

    /**
     * @return array{total:int, failed:int, handled:int, received:int, dispatched:int}
     */
    public function metrics(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT status, COUNT(*) AS cnt FROM mh_message GROUP BY status');

        $result = ['total' => 0, 'failed' => 0, 'handled' => 0, 'received' => 0, 'dispatched' => 0];

        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $count = (int) $row['cnt'];
            $result['total'] += $count;
            if (\array_key_exists($status, $result)) {
                $result[$status] = $count;
            }
        }

        return $result;
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
    private function mapRows(array $rows): array
    {
        return array_map($this->mapRow(...), $rows);
    }

    /**
     * @param array<string, scalar|null> $row
     *
     * @return array<string, array<array-key, scalar|null>|list<string>|scalar|null>
     */
    private function mapRow(array $row): array
    {
        $messageClass = (string) ($row['message_class'] ?? '');
        $messageType = \array_key_exists('message_type', $row)
            ? (string) $row['message_type']
            : $this->resolveMessageType($messageClass);
        $topicGroup = \array_key_exists('topic_group', $row)
            ? (string) $row['topic_group']
            : $this->resolveTopicGroup($messageClass);

        $row['message_name'] = (string) ($row['message_name'] ?? $this->resolveMessageName($messageClass));
        $row['message_type'] = $messageType;
        $row['topic_group'] = $topicGroup;
        $row['allowed_actions'] = $this->operatorActionPolicy->allowedActions((string) ($row['status'] ?? ''));
        $row['available_actions'] = $this->operatorActionPolicy->formatAllowedActions((string) ($row['status'] ?? ''));
        $row['status_label'] = $this->resolveStatusLabel((string) ($row['status'] ?? ''));
        $row['business_summary'] = $this->resolveBusinessSummary(
            $messageType,
            $topicGroup
        );
        $row['business_impact'] = $this->resolveBusinessImpact(
            (string) ($row['status'] ?? ''),
            $messageType,
            $topicGroup
        );

        return $row;
    }

    private function resolveMessageName(string $messageClass): string
    {
        $segments = explode('\\', $messageClass);

        return $segments[\count($segments) - 1];
    }

    private function resolveMessageType(string $messageClass): string
    {
        return match (true) {
            str_contains($messageClass, '\\Checkout\\Order\\') => 'Bestellung',
            str_contains($messageClass, '\\Checkout\\Payment\\') => 'Zahlung',
            str_contains($messageClass, '\\DataAbstractionLayer\\Indexing\\') => 'Indexer',
            str_contains($messageClass, '\\Content\\Flow\\') => 'Ablauf',
            str_contains($messageClass, '\\Content\\Mail\\') => 'E-Mail',
            str_contains($messageClass, '\\Framework\\Webhook\\') => 'Webhook',
            str_contains($messageClass, '\\Content\\Media\\') => 'Medien',
            str_contains($messageClass, 'MessengerHistoryDashboard\\') => 'Plugin',
            str_contains($messageClass, 'Swag\\PayPal\\') => 'PayPal',
            default => 'Sonstiges',
        };
    }

    private function resolveTopicGroup(string $messageClass): string
    {
        return match (true) {
            str_contains($messageClass, '\\Checkout\\Order\\') => 'Bestellungen',
            str_contains($messageClass, '\\Checkout\\Payment\\') => 'Zahlungen',
            str_contains($messageClass, '\\Content\\Mail\\') => 'Kommunikation',
            str_contains($messageClass, '\\Framework\\Webhook\\') => 'Integrationen',
            str_contains($messageClass, 'Swag\\PayPal\\') => 'Integrationen',
            str_contains($messageClass, '\\Content\\Media\\') => 'Inhalte',
            str_contains($messageClass, '\\Content\\Category\\') => 'Inhalte',
            str_contains($messageClass, '\\Content\\Rule\\') => 'Inhalte',
            default => 'System',
        };
    }

    private function resolveStatusLabel(string $status): string
    {
        return match ($status) {
            'dispatched' => 'Gesendet',
            'received' => 'Empfangen',
            'handled' => 'Verarbeitet',
            'failed' => 'Fehlgeschlagen',
            default => $status,
        };
    }

    private function resolveBusinessSummary(string $messageType, string $topicGroup): string
    {
        return match ($messageType) {
            'Bestellung' => 'Bearbeitet Hintergrundaufgaben rund um Bestellungen.',
            'Zahlung' => 'Bearbeitet Hintergrundaufgaben rund um Zahlungen und Zahlungsarten.',
            'Indexer' => 'Aktualisiert interne Shop-Daten und Suchindizes.',
            'Ablauf' => 'Führt automatisierte Abläufe und Regeln aus.',
            'E-Mail' => 'Bearbeitet E-Mail-Versand oder E-Mail-Folgen.',
            'Webhook' => 'Überträgt Daten an externe Systeme.',
            'Medien' => 'Verarbeitet Bilder und Mediendateien.',
            'Plugin' => 'Verarbeitet plugin-spezifische Hintergrundaufgaben.',
            'PayPal' => 'Verarbeitet PayPal-bezogene Hintergrundaufgaben.',
            default => match ($topicGroup) {
                'Bestellungen' => 'Bearbeitet Hintergrundaufgaben mit Bezug zu Bestellungen.',
                'Zahlungen' => 'Bearbeitet Hintergrundaufgaben mit Bezug zu Zahlungen.',
                'Kommunikation' => 'Bearbeitet Hintergrundaufgaben mit Bezug zu E-Mails oder Benachrichtigungen.',
                'Integrationen' => 'Bearbeitet Hintergrundaufgaben mit Bezug zu externen Systemen.',
                'Inhalte' => 'Bearbeitet Hintergrundaufgaben mit Bezug zu Inhalten und Katalogdaten.',
                default => 'Bearbeitet allgemeine Hintergrundaufgaben im Shop.',
            },
        };
    }

    private function resolveBusinessImpact(string $status, string $messageType, string $topicGroup): string
    {
        if ($status !== 'failed') {
            return 'Unkritisch';
        }

        return match ($messageType) {
            'Bestellung', 'Zahlung', 'Webhook', 'PayPal', 'E-Mail' => self::REVIEW_LABEL,
            'Indexer', 'Ablauf' => 'Beobachten',
            default => match ($topicGroup) {
                'Bestellungen', 'Zahlungen', 'Kommunikation', 'Integrationen' => self::REVIEW_LABEL,
                'Inhalte', 'System' => 'Beobachten',
                default => self::REVIEW_LABEL,
            },
        };
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
            $conditions[] = '(m.id LIKE :query OR m.message_class LIKE :query OR m.business_reference LIKE :query)';
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
                'Bestellungen' => "m.message_class LIKE '%\\\\Checkout\\\\Order\\\\%'",
                'Zahlungen' => "(m.message_class LIKE '%\\\\Checkout\\\\Payment\\\\%' OR m.message_class LIKE 'Swag\\\\PayPal\\\\%')",
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
