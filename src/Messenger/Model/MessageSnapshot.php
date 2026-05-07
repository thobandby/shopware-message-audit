<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Model;

final readonly class MessageSnapshot
{
    /**
     * @param array<string, array<array-key, scalar|null>|scalar|null> $payload
     * @param array<string, array<array-key, scalar|null>|scalar|null> $payloadPreview
     * @param array<string, array<array-key, scalar|null>|scalar|null> $headersPreview
     */
    public function __construct(
        public string $messageUuid,
        public string $messageClass,
        public ?string $messageName,
        public ?string $busName,
        public ?string $transportName,
        public ?string $transportMessageId,
        public ?string $correlationId,
        public ?string $causationId,
        public ?string $businessType,
        public ?string $businessReference,
        public string $status,
        public int $retryCount,
        public bool $isQuarantined,
        public array $payload,
        public array $payloadPreview,
        public array $headersPreview,
        public \DateTimeImmutable $firstSeenAt,
        public \DateTimeImmutable $lastSeenAt,
        public ?\DateTimeImmutable $firstHandledAt,
        public ?\DateTimeImmutable $finishedAt,
    ) {
    }
}
