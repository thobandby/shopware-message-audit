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
        $messageClass = (string) ($row['message_class'] ?? '');
        $source = (string) ($row['source'] ?? 'messenger');
        $subjectType = isset($row['subject_type']) ? (string) $row['subject_type'] : null;
        $messageType = $this->resolveMessageType($messageClass, $source, $subjectType, $locale);
        $topicGroup = $this->resolveTopicGroup($messageClass, $source, $subjectType, $locale);

        $row['message_name'] = $this->resolveMessageName($messageClass, $source, $subjectType, (string) ($row['status'] ?? ''), $locale);
        $row['message_type'] = $messageType;
        $row['topic_group'] = $topicGroup;
        $row['allowed_actions'] = $source === 'messenger' ? $this->operatorActionPolicy->allowedActions((string) ($row['status'] ?? '')) : [];
        $row['available_actions'] = $source === 'messenger'
            ? $this->operatorActionPolicy->formatAllowedActions((string) ($row['status'] ?? ''))
            : $this->translate($locale, 'Details', 'Details');
        $row['status_label'] = $this->resolveStatusLabel((string) ($row['status'] ?? ''), $source, $subjectType, $locale);
        $row['business_summary'] = $this->resolveBusinessSummary(
            $messageType,
            $topicGroup,
            $source,
            $locale
        );
        $row['business_impact'] = $this->resolveBusinessImpact(
            (string) ($row['status'] ?? ''),
            $messageType,
            $topicGroup,
            $source,
            $locale
        );
        $row['source_label'] = $source === 'state_change'
            ? $this->translate($locale, 'Statuswechsel', 'State change')
            : $this->translate($locale, 'Messenger', 'Messenger');

        return $row;
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
                $groupedRows[$groupKey] = $this->applyGroupedStateChangePresentation($row, $locale);

                continue;
            }

            $groupedRows[$groupKey]['group_count'] = (int) ($groupedRows[$groupKey]['group_count'] ?? 1) + 1;

            if ($this->shouldReplaceGroupedStateChange($groupedRows[$groupKey], $row)) {
                $row['group_count'] = (int) $groupedRows[$groupKey]['group_count'];
                $groupedRows[$groupKey] = $this->applyGroupedStateChangePresentation($row, $locale);
            }
        }

        return array_values($groupedRows);
    }

    /**
     * @param array<string, array<array-key, scalar|null>|list<string>|scalar|null> $groupedRow
     * @param array<string, array<array-key, scalar|null>|list<string>|scalar|null> $candidate
     */
    private function shouldReplaceGroupedStateChange(array $groupedRow, array $candidate): bool
    {
        $currentSubjectPriority = $this->stateChangeSubjectPriority((string) ($groupedRow['subject_type'] ?? ''));
        $candidateSubjectPriority = $this->stateChangeSubjectPriority((string) ($candidate['subject_type'] ?? ''));

        if ($candidateSubjectPriority > $currentSubjectPriority) {
            return true;
        }

        if ($candidateSubjectPriority < $currentSubjectPriority) {
            return false;
        }

        $currentCreatedAt = (string) ($groupedRow['created_at'] ?? '');
        $candidateCreatedAt = (string) ($candidate['created_at'] ?? '');

        if ($candidateCreatedAt > $currentCreatedAt) {
            return true;
        }

        if ($candidateCreatedAt < $currentCreatedAt) {
            return false;
        }

        return $this->stateChangeStatusPriority((string) ($candidate['status'] ?? ''))
            > $this->stateChangeStatusPriority((string) ($groupedRow['status'] ?? ''));
    }

    /**
     * @param array<string, array<array-key, scalar|null>|list<string>|scalar|null> $row
     *
     * @return array<string, array<array-key, scalar|null>|list<string>|scalar|null>
     */
    private function applyGroupedStateChangePresentation(array $row, string $locale): array
    {
        $businessReference = (string) ($row['business_reference'] ?? '');

        $row['message_name'] = $businessReference !== ''
            ? $this->translate($locale, 'Bestellung', 'Order') . ' ' . $businessReference
            : $this->translate($locale, 'Bestellung', 'Order');
        $row['message_type'] = $this->translate($locale, 'Bestellung', 'Order');
        $row['topic_group'] = $this->translate($locale, 'Bestellungen', 'Orders');
        $row['available_actions'] = $this->translate($locale, 'Details', 'Details');
        $row['allowed_actions'] = [];

        return $row;
    }

    private function stateChangeSubjectPriority(string $subjectType): int
    {
        return match ($subjectType) {
            'order' => 3,
            'order_delivery' => 2,
            'order_transaction' => 1,
            default => 0,
        };
    }

    private function stateChangeStatusPriority(string $status): int
    {
        return match ($status) {
            'completed', 'paid', 'shipped' => 3,
            'in_progress', 'reminded', 'shipped_partially' => 2,
            'open' => 1,
            default => 0,
        };
    }

    private function resolveMessageName(string $messageClass, string $source, ?string $subjectType, string $status, string $locale): string
    {
        if ($source === 'state_change') {
            return match ($subjectType) {
                'order' => $this->translate($locale, 'Bestellstatus', 'Order status') . ': ' . $status,
                'order_transaction' => $this->translate($locale, 'Zahlungsstatus', 'Payment status') . ': ' . $status,
                'order_delivery' => $this->translate($locale, 'Lieferstatus', 'Delivery status') . ': ' . $status,
                default => $this->translate($locale, 'Statuswechsel', 'State change') . ': ' . $status,
            };
        }

        $profile = $this->resolveMessageClassProfile($messageClass, $locale);

        if (isset($profile['name'])) {
            return $profile['name'];
        }

        $segments = explode('\\', $messageClass);

        return $this->humanizeClassName($segments[\count($segments) - 1]);
    }

    private function resolveMessageType(string $messageClass, string $source, ?string $subjectType, string $locale): string
    {
        if ($source === 'state_change') {
            return match ($subjectType) {
                'order' => $this->translate($locale, 'Bestellstatus', 'Order status'),
                'order_transaction' => $this->translate($locale, 'Zahlungsstatus', 'Payment status'),
                'order_delivery' => $this->translate($locale, 'Lieferstatus', 'Delivery status'),
                default => $this->translate($locale, 'Statuswechsel', 'State change'),
            };
        }

        $profile = $this->resolveMessageClassProfile($messageClass, $locale);

        if (isset($profile['type'])) {
            return $profile['type'];
        }

        return match (true) {
            str_contains($messageClass, '\\Checkout\\Order\\') => $this->translate($locale, 'Bestellung', 'Order'),
            str_contains($messageClass, '\\Checkout\\Payment\\') => $this->translate($locale, 'Zahlung', 'Payment'),
            str_contains($messageClass, '\\DataAbstractionLayer\\Indexing\\') => $this->translate($locale, 'Indexer', 'Indexer'),
            str_contains($messageClass, '\\Content\\Flow\\') => $this->translate($locale, 'Ablauf', 'Flow'),
            str_contains($messageClass, '\\Content\\Mail\\') => $this->translate($locale, 'E-Mail', 'Mail'),
            str_contains($messageClass, '\\Framework\\Webhook\\') => 'Webhook',
            str_contains($messageClass, '\\Content\\Media\\') => $this->translate($locale, 'Medien', 'Media'),
            str_contains($messageClass, '\\ScheduledTask\\') => $this->translate($locale, 'Geplanter Task', 'Scheduled task'),
            str_contains($messageClass, 'MessengerHistoryDashboard\\') => 'Plugin',
            str_contains($messageClass, 'Swag\\PayPal\\') => 'PayPal',
            default => $this->translate($locale, 'Sonstiges', 'Miscellaneous'),
        };
    }

    private function resolveTopicGroup(string $messageClass, string $source, ?string $subjectType, string $locale): string
    {
        if ($source === 'state_change') {
            return match ($subjectType) {
                'order' => $this->translate($locale, 'Bestellungen', 'Orders'),
                'order_transaction' => $this->translate($locale, 'Zahlungen', 'Payments'),
                'order_delivery' => $this->translate($locale, 'Bestellungen', 'Orders'),
                default => $this->translate($locale, 'System', 'System'),
            };
        }

        $profile = $this->resolveMessageClassProfile($messageClass, $locale);

        if (isset($profile['topic_group'])) {
            return $profile['topic_group'];
        }

        return match (true) {
            str_contains($messageClass, '\\Checkout\\Order\\') => $this->translate($locale, 'Bestellungen', 'Orders'),
            str_contains($messageClass, '\\Checkout\\Payment\\') => $this->translate($locale, 'Zahlungen', 'Payments'),
            str_contains($messageClass, '\\Content\\Mail\\') => $this->translate($locale, 'Kommunikation', 'Communication'),
            str_contains($messageClass, '\\Framework\\Webhook\\') => $this->translate($locale, 'Integrationen', 'Integrations'),
            str_contains($messageClass, 'Swag\\PayPal\\') => $this->translate($locale, 'Integrationen', 'Integrations'),
            str_contains($messageClass, '\\Content\\Media\\') => $this->translate($locale, 'Inhalte', 'Content'),
            str_contains($messageClass, '\\Content\\Category\\') => $this->translate($locale, 'Inhalte', 'Content'),
            str_contains($messageClass, '\\Content\\Rule\\') => $this->translate($locale, 'Inhalte', 'Content'),
            str_contains($messageClass, '\\Content\\ProductExport\\') => $this->translate($locale, 'Integrationen', 'Integrations'),
            str_contains($messageClass, '\\Content\\Sitemap\\') => $this->translate($locale, 'Inhalte', 'Content'),
            default => $this->translate($locale, 'System', 'System'),
        };
    }

    private function resolveStatusLabel(string $status, string $source, ?string $subjectType, string $locale): string
    {
        if ($source === 'state_change') {
            return match ($subjectType) {
                'order', 'order_transaction', 'order_delivery' => match ($status) {
                    'open' => $this->translate($locale, 'Offen', 'Open'),
                    'in_progress' => $this->translate($locale, 'In Bearbeitung', 'In progress'),
                    'completed' => $this->translate($locale, 'Abgeschlossen', 'Completed'),
                    'cancelled' => $this->translate($locale, 'Storniert', 'Cancelled'),
                    'paid' => $this->translate($locale, 'Bezahlt', 'Paid'),
                    'reminded' => $this->translate($locale, 'Erinnert', 'Reminded'),
                    'failed' => $this->translate($locale, 'Fehlgeschlagen', 'Failed'),
                    'shipped' => $this->translate($locale, 'Versandt', 'Shipped'),
                    'shipped_partially' => $this->translate($locale, 'Teilversandt', 'Partially shipped'),
                    default => $status,
                },
                default => $status,
            };
        }

        return match ($status) {
            'dispatched' => $this->translate($locale, 'Gesendet', 'Dispatched'),
            'received' => $this->translate($locale, 'Empfangen', 'Received'),
            'handled' => $this->translate($locale, 'Verarbeitet', 'Handled'),
            'failed' => $this->translate($locale, 'Fehlgeschlagen', 'Failed'),
            default => $status,
        };
    }

    private function resolveBusinessSummary(string $messageType, string $topicGroup, string $source, string $locale): string
    {
        if ($source === 'state_change') {
            return match ($messageType) {
                $this->translate($locale, 'Bestellstatus', 'Order status') => $this->translate($locale, 'Dokumentiert synchrone Statuswechsel einer Bestellung.', 'Documents synchronous status changes of an order.'),
                $this->translate($locale, 'Zahlungsstatus', 'Payment status') => $this->translate($locale, 'Dokumentiert synchrone Statuswechsel einer Zahlungstransaktion.', 'Documents synchronous status changes of a payment transaction.'),
                $this->translate($locale, 'Lieferstatus', 'Delivery status') => $this->translate($locale, 'Dokumentiert synchrone Statuswechsel einer Lieferung.', 'Documents synchronous status changes of a delivery.'),
                default => $this->translate($locale, 'Dokumentiert synchrone Statuswechsel im Shop.', 'Documents synchronous status changes in the shop.'),
            };
        }

        return match ($messageType) {
            $this->translate($locale, 'Bestellung', 'Order') => $this->translate($locale, 'Bearbeitet Hintergrundaufgaben rund um Bestellungen.', 'Processes background tasks related to orders.'),
            $this->translate($locale, 'Zahlung', 'Payment') => $this->translate($locale, 'Bearbeitet Hintergrundaufgaben rund um Zahlungen und Zahlungsarten.', 'Processes background tasks related to payments and payment methods.'),
            $this->translate($locale, 'Indexer', 'Indexer') => $this->translate($locale, 'Aktualisiert interne Shop-Daten und Suchindizes.', 'Updates internal shop data and search indexes.'),
            $this->translate($locale, 'Ablauf', 'Flow') => $this->translate($locale, 'Führt automatisierte Abläufe und Regeln aus.', 'Executes automated flows and rules.'),
            $this->translate($locale, 'E-Mail', 'Mail') => $this->translate($locale, 'Bearbeitet E-Mail-Versand oder E-Mail-Folgen.', 'Processes mail delivery or follow-up mails.'),
            'Webhook' => $this->translate($locale, 'Überträgt Daten an externe Systeme.', 'Transfers data to external systems.'),
            $this->translate($locale, 'Medien', 'Media') => $this->translate($locale, 'Verarbeitet Bilder und Mediendateien.', 'Processes images and media files.'),
            $this->translate($locale, 'Produkt-Export', 'Product export') => $this->translate($locale, 'Erzeugt Exportdateien für Produktfeeds und externe Kanäle.', 'Generates export files for product feeds and external channels.'),
            'Sitemap' => $this->translate($locale, 'Erzeugt oder aktualisiert Sitemaps für den Shop.', 'Generates or updates sitemaps for the shop.'),
            $this->translate($locale, 'Geplanter Task', 'Scheduled task') => $this->translate($locale, 'Führt eine geplante Hintergrundaufgabe im Shop aus.', 'Executes a scheduled background task in the shop.'),
            'Plugin' => $this->translate($locale, 'Verarbeitet plugin-spezifische Hintergrundaufgaben.', 'Processes plugin-specific background tasks.'),
            'PayPal' => $this->translate($locale, 'Verarbeitet PayPal-bezogene Hintergrundaufgaben.', 'Processes PayPal-related background tasks.'),
            default => match ($topicGroup) {
                $this->translate($locale, 'Bestellungen', 'Orders') => $this->translate($locale, 'Bearbeitet Hintergrundaufgaben mit Bezug zu Bestellungen.', 'Processes background tasks related to orders.'),
                $this->translate($locale, 'Zahlungen', 'Payments') => $this->translate($locale, 'Bearbeitet Hintergrundaufgaben mit Bezug zu Zahlungen.', 'Processes background tasks related to payments.'),
                $this->translate($locale, 'Kommunikation', 'Communication') => $this->translate($locale, 'Bearbeitet Hintergrundaufgaben mit Bezug zu E-Mails oder Benachrichtigungen.', 'Processes background tasks related to mails or notifications.'),
                $this->translate($locale, 'Integrationen', 'Integrations') => $this->translate($locale, 'Bearbeitet Hintergrundaufgaben mit Bezug zu externen Systemen.', 'Processes background tasks related to external systems.'),
                $this->translate($locale, 'Inhalte', 'Content') => $this->translate($locale, 'Bearbeitet Hintergrundaufgaben mit Bezug zu Inhalten und Katalogdaten.', 'Processes background tasks related to content and catalog data.'),
                default => $this->translate($locale, 'Bearbeitet allgemeine Hintergrundaufgaben im Shop.', 'Processes general background tasks in the shop.'),
            },
        };
    }

    /**
     * @return array{name?:string, type?:string, topic_group?:string}
     */
    private function resolveMessageClassProfile(string $messageClass, string $locale): array
    {
        $exactProfiles = [
            'Shopware\Core\Content\ProductExport\ScheduledTask\ProductExportGenerateTask' => [
                'name' => $this->translate($locale, 'Produkt-Export erzeugen', 'Generate product export'),
                'type' => $this->translate($locale, 'Produkt-Export', 'Product export'),
                'topic_group' => $this->translate($locale, 'Integrationen', 'Integrations'),
            ],
        ];

        if (isset($exactProfiles[$messageClass])) {
            return $exactProfiles[$messageClass];
        }

        $prefixProfiles = [
            'Shopware\Core\Content\ProductExport\\' => [
                'type' => $this->translate($locale, 'Produkt-Export', 'Product export'),
                'topic_group' => $this->translate($locale, 'Integrationen', 'Integrations'),
            ],
            'Shopware\Core\Content\Sitemap\\' => [
                'type' => 'Sitemap',
                'topic_group' => $this->translate($locale, 'Inhalte', 'Content'),
            ],
            'Shopware\Core\Content\Media\\' => [
                'type' => $this->translate($locale, 'Medien', 'Media'),
                'topic_group' => $this->translate($locale, 'Inhalte', 'Content'),
            ],
            'Shopware\Core\Content\Mail\\' => [
                'type' => $this->translate($locale, 'E-Mail', 'Mail'),
                'topic_group' => $this->translate($locale, 'Kommunikation', 'Communication'),
            ],
            'Shopware\Core\Content\Flow\\' => [
                'type' => $this->translate($locale, 'Ablauf', 'Flow'),
                'topic_group' => $this->translate($locale, 'Bestellungen', 'Orders'),
            ],
            'Shopware\Core\Framework\Webhook\\' => [
                'type' => 'Webhook',
                'topic_group' => $this->translate($locale, 'Integrationen', 'Integrations'),
            ],
            'Shopware\Core\Framework\DataAbstractionLayer\Indexing\\' => [
                'type' => $this->translate($locale, 'Indexer', 'Indexer'),
                'topic_group' => $this->translate($locale, 'System', 'System'),
            ],
            'Shopware\Core\Checkout\Order\\' => [
                'type' => $this->translate($locale, 'Bestellung', 'Order'),
                'topic_group' => $this->translate($locale, 'Bestellungen', 'Orders'),
            ],
            'Shopware\Core\Checkout\Payment\\' => [
                'type' => $this->translate($locale, 'Zahlung', 'Payment'),
                'topic_group' => $this->translate($locale, 'Zahlungen', 'Payments'),
            ],
            'Swag\PayPal\\' => [
                'type' => 'PayPal',
                'topic_group' => $this->translate($locale, 'Integrationen', 'Integrations'),
            ],
            'MessengerHistoryDashboard\\' => [
                'type' => 'Plugin',
                'topic_group' => $this->translate($locale, 'System', 'System'),
            ],
        ];

        foreach ($prefixProfiles as $prefix => $profile) {
            if (! str_starts_with($messageClass, $prefix)) {
                continue;
            }

            return $profile;
        }

        if (str_contains($messageClass, '\\ScheduledTask\\')) {
            $segments = explode('\\', $messageClass);
            $baseName = $segments[\count($segments) - 1];

            return [
                'name' => $this->humanizeClassName($baseName),
                'type' => $this->translate($locale, 'Geplanter Task', 'Scheduled task'),
                'topic_group' => $this->translate($locale, 'System', 'System'),
            ];
        }

        return [];
    }

    private function humanizeClassName(string $className): string
    {
        $trimmedClassName = preg_replace('/(Message|Task|Event|Handler)$/', '', $className) ?? $className;
        $humanized = preg_replace('/(?<!^)([A-Z])/', ' $1', $trimmedClassName) ?? $trimmedClassName;

        return trim($humanized);
    }

    private function resolveBusinessImpact(string $status, string $messageType, string $topicGroup, string $source, string $locale): string
    {
        $reviewLabel = $this->translate($locale, 'Prüfen', 'Review');
        $monitorLabel = $this->translate($locale, 'Beobachten', 'Monitor');
        $lowLabel = $this->translate($locale, 'Unkritisch', 'Low');

        if ($source === 'state_change') {
            return match ($status) {
                'failed', 'cancelled' => $reviewLabel,
                'reminded', 'in_progress', 'shipped_partially' => $monitorLabel,
                default => $lowLabel,
            };
        }

        if ($status !== 'failed') {
            return $lowLabel;
        }

        return match ($messageType) {
            $this->translate($locale, 'Bestellung', 'Order'),
            $this->translate($locale, 'Zahlung', 'Payment'),
            'Webhook',
            'PayPal',
            $this->translate($locale, 'E-Mail', 'Mail') => $reviewLabel,
            $this->translate($locale, 'Indexer', 'Indexer'),
            $this->translate($locale, 'Ablauf', 'Flow') => $monitorLabel,
            default => match ($topicGroup) {
                $this->translate($locale, 'Bestellungen', 'Orders'),
                $this->translate($locale, 'Zahlungen', 'Payments'),
                $this->translate($locale, 'Kommunikation', 'Communication'),
                $this->translate($locale, 'Integrationen', 'Integrations') => $reviewLabel,
                $this->translate($locale, 'Inhalte', 'Content'),
                $this->translate($locale, 'System', 'System') => $monitorLabel,
                default => $reviewLabel,
            },
        };
    }

    private function translate(string $locale, string $de, string $en): string
    {
        return str_starts_with(strtolower($locale), 'en') ? $en : $de;
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
