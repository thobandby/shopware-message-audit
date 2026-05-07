<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Repository;

use Doctrine\DBAL\Connection;
use Thorsten\MessengerHistory\Messenger\Contract\MessageAuditRepositoryInterface;
use Thorsten\MessengerHistory\Messenger\Contract\MessageSearchCriteriaInterface;
use Thorsten\MessengerHistory\Messenger\Model\FailureSnapshot;
use Thorsten\MessengerHistory\Messenger\Model\MessageSnapshot;
use Thorsten\MessengerHistory\Messenger\Model\TransitionRecord;

final class DbMessageAuditRepository implements MessageAuditRepositoryInterface
{
    /** @readonly */
    private Connection $connection;

    public function __construct(
        Connection $connection
    ) {
        $this->connection = $connection;
    }

    public function upsertMessage(MessageSnapshot $messageSnapshot): void
    {
        $payload = [
            'message_uuid' => $messageSnapshot->messageUuid,
            'message_class' => $messageSnapshot->messageClass,
            'message_name' => $messageSnapshot->messageName,
            'bus_name' => $messageSnapshot->busName,
            'status' => $messageSnapshot->status,
            'transport_name' => $messageSnapshot->transportName,
            'transport_message_id' => $messageSnapshot->transportMessageId,
            'correlation_id' => $messageSnapshot->correlationId,
            'causation_id' => $messageSnapshot->causationId,
            'business_type' => $messageSnapshot->businessType,
            'business_reference' => $messageSnapshot->businessReference,
            'retry_count' => $messageSnapshot->retryCount,
            'is_quarantined' => (int) $messageSnapshot->isQuarantined,
            'payload_json' => $this->encodeJson($messageSnapshot->payload),
            'payload_preview' => $this->encodeJson($messageSnapshot->payloadPreview),
            'headers_preview' => $this->encodeJson($messageSnapshot->headersPreview),
            'created_at' => $messageSnapshot->firstSeenAt->format('Y-m-d H:i:s.v'),
            'updated_at' => $messageSnapshot->lastSeenAt->format('Y-m-d H:i:s.v'),
            'first_handled_at' => $messageSnapshot->firstHandledAt?->format('Y-m-d H:i:s.v'),
            'finished_at' => $messageSnapshot->finishedAt?->format('Y-m-d H:i:s.v'),
        ];

        $this->connection->executeStatement(
            'INSERT INTO mh_message (
                id,
                message_uuid,
                message_class,
                message_name,
                bus_name,
                status,
                transport_name,
                transport_message_id,
                correlation_id,
                causation_id,
                business_type,
                business_reference,
                retry_count,
                is_quarantined,
                payload_json,
                payload_preview,
                headers_preview,
                first_handled_at,
                finished_at,
                created_at,
                updated_at
             )
             VALUES (
                UUID_TO_BIN(UUID()),
                :message_uuid,
                :message_class,
                :message_name,
                :bus_name,
                :status,
                :transport_name,
                :transport_message_id,
                :correlation_id,
                :causation_id,
                :business_type,
                :business_reference,
                :retry_count,
                :is_quarantined,
                :payload_json,
                :payload_preview,
                :headers_preview,
                :first_handled_at,
                :finished_at,
                :created_at,
                :updated_at
             )
             ON DUPLICATE KEY UPDATE 
                message_name = VALUES(message_name),
                bus_name = VALUES(bus_name),
                status = VALUES(status), 
                transport_name = VALUES(transport_name), 
                transport_message_id = VALUES(transport_message_id),
                correlation_id = VALUES(correlation_id),
                causation_id = VALUES(causation_id),
                business_type = VALUES(business_type),
                business_reference = VALUES(business_reference),
                retry_count = VALUES(retry_count), 
                is_quarantined = VALUES(is_quarantined),
                payload_json = VALUES(payload_json),
                payload_preview = VALUES(payload_preview),
                headers_preview = VALUES(headers_preview),
                first_handled_at = VALUES(first_handled_at),
                finished_at = VALUES(finished_at),
                updated_at = VALUES(updated_at)',
            $payload
        );
    }

    public function appendTransition(TransitionRecord $transitionRecord): void
    {
        $this->connection->executeStatement(
            'INSERT INTO mh_transition (id, message_uuid, from_status, to_status, event_name, transport_name, worker_name, duration_ms, details, occurred_at)
             VALUES (UUID_TO_BIN(UUID()), :message_uuid, :from_status, :to_status, :event_name, :transport_name, :worker_name, :duration_ms, :details, :occurred_at)',
            [
                'message_uuid' => $transitionRecord->getMessageUuid(),
                'from_status' => $transitionRecord->getFromStatus(),
                'to_status' => $transitionRecord->getToStatus(),
                'event_name' => $transitionRecord->getEventName(),
                'transport_name' => $transitionRecord->getTransportName(),
                'worker_name' => $transitionRecord->getWorkerName(),
                'duration_ms' => $transitionRecord->getDurationMs(),
                'details' => json_encode($transitionRecord->getDetails()),
                'occurred_at' => $transitionRecord->getOccurredAt()->format('Y-m-d H:i:s.v'),
            ]
        );
    }

    public function appendFailure(FailureSnapshot $failureSnapshot): void
    {
        $this->connection->executeStatement(
            'INSERT INTO mh_failure (id, message_uuid, exception_class, exception_message, error_hash, error_details, stacktrace, failed_at)
             VALUES (UUID_TO_BIN(UUID()), :message_uuid, :exception_class, :exception_message, :error_hash, :error_details, :stacktrace, :failed_at)',
            [
                'message_uuid' => $failureSnapshot->getMessageUuid(),
                'exception_class' => $failureSnapshot->getExceptionClass(),
                'exception_message' => $failureSnapshot->getExceptionMessage(),
                'error_hash' => $failureSnapshot->getErrorHash(),
                'error_details' => json_encode($failureSnapshot->getErrorDetails()),
                'stacktrace' => $failureSnapshot->getStacktrace(),
                'failed_at' => $failureSnapshot->getFailedAt()->format('Y-m-d H:i:s.v'),
            ]
        );
    }

    public function findByMessageUuid(string $messageUuid): ?MessageSnapshot
    {
        $data = $this->connection->fetchAssociative(
            'SELECT * FROM mh_message WHERE message_uuid = :message_uuid',
            ['message_uuid' => $messageUuid]
        );

        if (! $data) {
            return null;
        }

        return $this->mapRowToSnapshot($data);
    }

    /**
     * @return list<MessageSnapshot>
     */
    public function search(MessageSearchCriteriaInterface $criteria): array
    {
        [$whereClause, $params] = $this->buildSearchQueryParts($criteria);
        $data = $this->connection->fetchAllAssociative(
            'SELECT * FROM mh_message' . $whereClause . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset',
            $params
        );

        $results = [];
        foreach ($data as $row) {
            $results[] = $this->mapRowToSnapshot($row);
        }

        return $results;
    }

    /**
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function buildSearchQueryParts(MessageSearchCriteriaInterface $criteria): array
    {
        $conditions = [];
        $params = [
            'limit' => $criteria->getLimit(),
            'offset' => $criteria->getOffset(),
        ];

        $this->addFilter($conditions, $params, 'status', $criteria->getStatus());
        $this->addFilter($conditions, $params, 'transport_name', $criteria->getTransportName());
        $this->addFilter($conditions, $params, 'message_class', $criteria->getMessageClass());
        $this->addFilter($conditions, $params, 'business_reference', $criteria->getBusinessReference());

        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $params];
    }

    /**
     * @param list<string> $conditions
     * @param array<string, int|string> $params
     */
    private function addFilter(array &$conditions, array &$params, string $column, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $conditions[] = $column . ' = :' . $column;
        $params[$column] = $value;
    }

    /**
     * @return array<string, array<array-key, scalar|null>|scalar|null>
     */
    private function decodePayloadPreview(?string $payloadPreview): array
    {
        $json = $payloadPreview !== null && $payloadPreview !== '' ? $payloadPreview : '[]';

        return json_decode($json, true);
    }

    /**
     * @param array<string, array<array-key, scalar|null>|scalar|null> $value
     */
    private function encodeJson(array $value): string
    {
        return (string) json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function parseNullableDateTime(string|int|float|bool|null $value): ?\DateTimeImmutable
    {
        return \is_string($value) && $value !== ''
            ? new \DateTimeImmutable($value)
            : null;
    }

    /**
     * @param array<string, scalar|null> $row
     */
    private function mapRowToSnapshot(array $row): MessageSnapshot
    {
        return new MessageSnapshot(
            messageUuid: (string) $row['message_uuid'],
            messageClass: (string) $row['message_class'],
            messageName: isset($row['message_name']) ? (string) $row['message_name'] : null,
            busName: isset($row['bus_name']) ? (string) $row['bus_name'] : null,
            transportName: isset($row['transport_name']) ? (string) $row['transport_name'] : null,
            transportMessageId: isset($row['transport_message_id']) ? (string) $row['transport_message_id'] : null,
            correlationId: isset($row['correlation_id']) ? (string) $row['correlation_id'] : null,
            causationId: isset($row['causation_id']) ? (string) $row['causation_id'] : null,
            businessType: isset($row['business_type']) ? (string) $row['business_type'] : null,
            businessReference: isset($row['business_reference']) ? (string) $row['business_reference'] : null,
            status: (string) $row['status'],
            retryCount: (int) $row['retry_count'],
            isQuarantined: (bool) ($row['is_quarantined'] ?? false),
            payload: $this->decodePayloadPreview(isset($row['payload_json']) ? (string) $row['payload_json'] : null),
            payloadPreview: $this->decodePayloadPreview(isset($row['payload_preview']) ? (string) $row['payload_preview'] : null),
            headersPreview: $this->decodePayloadPreview(isset($row['headers_preview']) ? (string) $row['headers_preview'] : null),
            firstSeenAt: new \DateTimeImmutable((string) $row['created_at']),
            lastSeenAt: new \DateTimeImmutable(isset($row['updated_at']) ? (string) $row['updated_at'] : (string) $row['created_at']),
            firstHandledAt: $this->parseNullableDateTime($row['first_handled_at'] ?? null),
            finishedAt: $this->parseNullableDateTime($row['finished_at'] ?? null),
        );
    }
}
