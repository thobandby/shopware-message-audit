<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Monitoring;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Thorsten\MessengerHistory\Messenger\Contract\MessageAuditRepositoryInterface;
use Thorsten\MessengerHistory\Messenger\Contract\OperatorActionExecutorInterface;
use Thorsten\MessengerHistory\Messenger\Model\OperatorActionRequest;
use Thorsten\MessengerHistory\Messenger\Model\OperatorActionResult;
use Thorsten\MessengerHistory\Messenger\Stamp\CausationIdStamp;
use Thorsten\MessengerHistory\Messenger\Stamp\CorrelationIdStamp;
use Thorsten\MessengerHistory\Messenger\Stamp\MessageUuidStamp;

final class SymfonyMessengerOperatorActionExecutor implements OperatorActionExecutorInterface
{
    /** @readonly */
    private MessageBusInterface $messageBus;

    /** @readonly */
    private MessageAuditRepositoryInterface $auditRepository;

    public function __construct(
        MessageBusInterface $messageBus,
        MessageAuditRepositoryInterface $auditRepository,
        private readonly PayloadHydrator $payloadHydrator,
    ) {
        $this->messageBus = $messageBus;
        $this->auditRepository = $auditRepository;
    }

    public function retryNow(OperatorActionRequest $request): OperatorActionResult
    {
        return $this->dispatchRetry($request);
    }

    public function retryDelayed(OperatorActionRequest $request, int $delayMs): OperatorActionResult
    {
        return $this->dispatchRetry($request, [new DelayStamp($delayMs)]);
    }

    public function quarantine(OperatorActionRequest $request): OperatorActionResult
    {
        return new OperatorActionResult(
            $this->generateUuid(),
            'accepted',
            $request->messageUuid,
            'quarantined'
        );
    }

    public function dismiss(OperatorActionRequest $request): OperatorActionResult
    {
        return new OperatorActionResult(
            $this->generateUuid(),
            'accepted',
            $request->messageUuid,
            'dismissed'
        );
    }

    public function requeueToTransport(OperatorActionRequest $request, string $targetTransport): OperatorActionResult
    {
        // Symfony bietet keinen standardmäßigen Weg, um den Transport beim Dispatching zu erzwingen,
        // außer über spezifische Transport-Stamps (wenn unterstützt).
        return $this->dispatchRetry($request, [], ['target_transport' => $targetTransport]);
    }

    /**
     * @param list<object> $additionalStamps
     * @param array<string, array<array-key, scalar|null>|scalar|null> $details
     */
    private function dispatchRetry(OperatorActionRequest $request, array $additionalStamps = [], array $details = []): OperatorActionResult
    {
        $snapshot = $this->auditRepository->findByMessageUuid($request->messageUuid);
        if ($snapshot === null) {
            return new OperatorActionResult($this->generateUuid(), 'failed', $request->messageUuid, null, ['error' => 'Snapshot not found']);
        }

        try {
            if ($snapshot->payload === []) {
                return new OperatorActionResult(
                    $this->generateUuid(),
                    'failed',
                    $request->messageUuid,
                    null,
                    ['error' => 'Message payload is not available for retry']
                );
            }

            $message = $this->payloadHydrator->hydrate($snapshot->messageClass, $snapshot->payload);
            $redispatchedMessageUuid = $this->generateUuid();
            $stamps = [
                new MessageUuidStamp($redispatchedMessageUuid),
                new CausationIdStamp($request->messageUuid),
                new CorrelationIdStamp($snapshot->correlationId ?? $request->messageUuid),
                ...$additionalStamps,
            ];
            $this->messageBus->dispatch($message, $stamps);

            /** @var array<string, array<array-key, scalar|null>|scalar|null> $mergedDetails */
            $mergedDetails = array_merge($details, [
                'message_class' => $snapshot->messageClass,
                'redispatched_message_uuid' => $redispatchedMessageUuid,
            ]);
            return new OperatorActionResult(
                $this->generateUuid(),
                'accepted',
                $request->messageUuid,
                'retry_requested',
                $mergedDetails
            );
        } catch (\Throwable $e) {
            return new OperatorActionResult(
                $this->generateUuid(),
                'failed',
                $request->messageUuid,
                null,
                ['error' => (string) $e->getMessage()]
            );
        }
    }

    private function generateUuid(): string
    {
        return bin2hex(random_bytes(16));
    }
}
