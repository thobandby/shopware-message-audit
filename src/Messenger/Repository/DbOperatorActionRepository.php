<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Repository;

use Doctrine\DBAL\Connection;
use Thorsten\MessengerHistory\Messenger\Contract\OperatorActionRepositoryInterface;
use Thorsten\MessengerHistory\Messenger\Model\OperatorActionRecord;
use Thorsten\MessengerHistory\Messenger\Model\OperatorIdentity;

final class DbOperatorActionRepository implements OperatorActionRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function append(OperatorActionRecord $record): void
    {
        $this->connection->executeStatement(
            'INSERT INTO mh_operator_action (id, action_uuid, message_uuid, action_type, old_status, new_status, old_transport, new_transport, operator_user_id, operator_display_name, reason_code, reason_text, result, result_details, executed_at)
             VALUES (UUID_TO_BIN(UUID()), :action_uuid, :message_uuid, :action_type, :old_status, :new_status, :old_transport, :new_transport, :operator_user_id, :operator_display_name, :reason_code, :reason_text, :result, :result_details, :executed_at)',
            [
                'action_uuid' => $record->getActionUuid(),
                'message_uuid' => $record->getMessageUuid(),
                'action_type' => $record->getActionType(),
                'old_status' => $record->getOldStatus(),
                'new_status' => $record->getNewStatus(),
                'old_transport' => $record->getOldTransport(),
                'new_transport' => $record->getNewTransport(),
                'operator_user_id' => $record->getOperator()?->userId,
                'operator_display_name' => $record->getOperator()?->displayName,
                'reason_code' => $record->getReasonCode(),
                'reason_text' => $record->getReasonText(),
                'result' => $record->getResult(),
                'result_details' => json_encode($record->getResultDetails()),
                'executed_at' => $record->getExecutedAt()->format('Y-m-d H:i:s.v'),
            ]
        );
    }

    /**
     * @return list<OperatorActionRecord>
     */
    public function findByMessageUuid(string $messageUuid): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM mh_operator_action WHERE message_uuid = :message_uuid ORDER BY executed_at DESC',
            ['message_uuid' => $messageUuid]
        );

        $results = [];
        foreach ($rows as $row) {
            $results[] = new OperatorActionRecord(
                actionUuid: $row['action_uuid'],
                messageUuid: $row['message_uuid'],
                actionType: $row['action_type'],
                oldStatus: $row['old_status'],
                newStatus: $row['new_status'],
                oldTransport: $row['old_transport'],
                newTransport: $row['new_transport'],
                operator: new OperatorIdentity(
                    userId: $row['operator_user_id'],
                    email: null,
                    displayName: $row['operator_display_name']
                ),
                reasonCode: $row['reason_code'],
                reasonText: $row['reason_text'],
                requestId: null,
                uiSessionId: null,
                overridePayload: null,
                result: $row['result'],
                resultDetails: $this->decodeResultDetails($row['result_details'] ?? null),
                approvedByUserId: null,
                approvedAt: null,
                executedAt: new \DateTimeImmutable($row['executed_at']),
            );
        }

        return $results;
    }

    /**
     * @return array<string, array<array-key, scalar|null>|scalar|null>
     */
    private function decodeResultDetails(?string $resultDetails): array
    {
        $json = $resultDetails !== null && $resultDetails !== '' ? $resultDetails : '[]';

        return json_decode($json, true);
    }
}
