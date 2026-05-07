<?php declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class MessageRepository
{
    public function __construct(private readonly Connection $connection) {}

    /**
     * @return array{data:list<array<string, mixed>>, total:int, page:int, limit:int}
     */
    public function list(
        ?string $status = null,
        ?string $query = null,
        int $page = 1,
        int $limit = 25,
        ?string $topicGroup = null,
        ?\DateTimeImmutable $createdFrom = null,
        ?\DateTimeImmutable $createdTo = null
    ): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $baseSql = <<<'SQL'
FROM mh_message m
SQL;

        $where = $this->buildWhereClause($status, $query, $topicGroup, $createdFrom, $createdTo);
        $parameters = $where['parameters'];

        $selectSql = <<<'SQL'
SELECT
    m.id,
    m.message_class,
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
            'SELECT id, message_class, payload_json, status, retry_count, created_at, updated_at FROM mh_message WHERE id = :id',
            ['id' => $id]
        );

        if ($row === false) {
            return false;
        }

        return $this->mapRow($row);
    }

    public function insertIfMissing(string $id, string $class, string $payloadJson, string $status): void
    {
        if ($this->connection->fetchOne('SELECT id FROM mh_message WHERE id = :id', ['id' => $id]) !== false) {
            return;
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->connection->insert('mh_message', [
            'id' => $id,
            'message_class' => $class,
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
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function incrementRetryCount(string $id): void
    {
        $this->connection->executeStatement(
            'UPDATE mh_message SET retry_count = retry_count + 1, updated_at = :updatedAt WHERE id = :id',
            ['id' => $id, 'updatedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]
        );
    }

    public function metrics(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT status, COUNT(*) AS cnt FROM mh_message GROUP BY status');

        $result = ['total' => 0, 'failed' => 0, 'handled' => 0, 'received' => 0, 'dispatched' => 0];

        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $count = (int) $row['cnt'];
            $result['total'] += $count;
            if (array_key_exists($status, $result)) {
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
        $formattedCutoff = $cutoff->format('Y-m-d H:i:s');

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
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function mapRows(array $rows): array
    {
        return array_map($this->mapRow(...), $rows);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function mapRow(array $row): array
    {
        $row['message_name'] = (string) ($row['message_name'] ?? $this->resolveMessageName((string) ($row['message_class'] ?? '')));
        $row['message_type'] = (string) ($row['message_type'] ?? $this->resolveMessageType((string) ($row['message_class'] ?? '')));
        $row['topic_group'] = (string) ($row['topic_group'] ?? $this->resolveTopicGroup((string) ($row['message_class'] ?? '')));
        $row['available_actions'] = $this->resolveAvailableActions((string) ($row['status'] ?? ''));
        $row['status_label'] = $this->resolveStatusLabel((string) ($row['status'] ?? ''));
        $row['business_summary'] = $this->resolveBusinessSummary(
            (string) ($row['message_type'] ?? ''),
            (string) ($row['topic_group'] ?? '')
        );
        $row['business_impact'] = $this->resolveBusinessImpact(
            (string) ($row['status'] ?? ''),
            (string) ($row['message_type'] ?? ''),
            (string) ($row['topic_group'] ?? '')
        );

        return $row;
    }

    private function resolveMessageName(string $messageClass): string
    {
        $segments = explode('\\', $messageClass);

        return end($segments) ?: $messageClass;
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

    private function resolveAvailableActions(string $status): string
    {
        return match ($status) {
            'failed' => 'Erneut senden, Ausblenden, Erledigen',
            'received', 'dispatched' => 'Ausblenden, Erledigen',
            'handled' => 'Erledigen',
            default => 'Details',
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
            'Bestellung', 'Zahlung', 'Webhook', 'PayPal', 'E-Mail' => 'Prüfen',
            'Indexer', 'Ablauf' => 'Beobachten',
            default => match ($topicGroup) {
                'Bestellungen', 'Zahlungen', 'Kommunikation', 'Integrationen' => 'Prüfen',
                'Inhalte', 'System' => 'Beobachten',
                default => 'Prüfen',
            },
        };
    }

    /**
     * @return array{sql:string, parameters:array<string, mixed>}
     */
    private function buildWhereClause(
        ?string $status,
        ?string $query,
        ?string $topicGroup,
        ?\DateTimeImmutable $createdFrom,
        ?\DateTimeImmutable $createdTo
    ): array {
        $conditions = [];
        $parameters = [];

        if ($status !== null) {
            $conditions[] = 'm.status = :status';
            $parameters['status'] = $status;
        }

        if ($createdFrom !== null) {
            $conditions[] = 'm.created_at >= :createdFrom';
            $parameters['createdFrom'] = $createdFrom->format('Y-m-d H:i:s');
        }

        if ($createdTo !== null) {
            $conditions[] = 'm.created_at <= :createdTo';
            $parameters['createdTo'] = $createdTo->format('Y-m-d H:i:s');
        }

        if ($query !== null && $query !== '') {
            $conditions[] = "(m.id LIKE :query OR m.message_class LIKE :query)";
            $parameters['query'] = '%' . $query . '%';
        }

        if ($topicGroup !== null && $topicGroup !== '') {
            $conditions[] = match ($topicGroup) {
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
