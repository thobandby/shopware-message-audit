<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Service;

use MessengerHistoryDashboard\Core\Messenger\Stamp\CausationIdStamp;
use MessengerHistoryDashboard\Core\Messenger\Stamp\CorrelationIdStamp;
use MessengerHistoryDashboard\Core\Messenger\Stamp\MessageUuidStamp;
use MessengerHistoryDashboard\Core\Messenger\Util\MessageUuidResolver;
use MessengerHistoryDashboard\Core\Operator\OperatorIdentity;
use MessengerHistoryDashboard\Core\Repository\MessageRepository;
use MessengerHistoryDashboard\Core\Repository\OperatorActionRepository;
use Symfony\Component\Messenger\MessageBusInterface;

final class ReplayService
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly PayloadHydrator $payloadHydrator,
        private readonly MessageBusInterface $messageBus,
        private readonly MessageAuditWriter $messageAuditWriter,
        private readonly OperatorActionRepository $operatorActionRepository
    ) {
    }

    public function retry(string $messageId, string $reason, ?OperatorIdentity $operator = null): string
    {
        $message = $this->messageRepository->find($messageId);

        if ($message === false) {
            throw new \RuntimeException('Message not found: ' . $messageId);
        }

        $payload = json_decode((string) $message['payload_json'], true, 512, JSON_THROW_ON_ERROR);
        if (!\is_array($payload)) {
            throw new \RuntimeException('Invalid payload for message: ' . $messageId);
        }

        $newUuid = MessageUuidResolver::generate();
        $object = $this->payloadHydrator->hydrate((string) $message['message_class'], $payload);
        $correlationId = (string) ($message['correlation_id'] ?? $messageId);

        $this->operatorActionRepository->insert($messageId, 'retry_now', $reason, $operator);
        $this->messageAuditWriter->recordRetry($messageId);
        $this->messageBus->dispatch($object, [
            new MessageUuidStamp($newUuid),
            new CorrelationIdStamp($correlationId),
            new CausationIdStamp($messageId),
        ]);

        return $newUuid;
    }
}
