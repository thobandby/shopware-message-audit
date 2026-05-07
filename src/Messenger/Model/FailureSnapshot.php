<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Model;

final class FailureSnapshot
{
    /**
     * @param array<string, array<bool|float|int|string|null>> $errorDetails
     */
    public function __construct(
        private readonly string $messageUuid,
        private readonly string $exceptionClass,
        private readonly string $exceptionMessage,
        private readonly string $errorHash,
        private readonly array $errorDetails,
        private readonly ?string $stacktrace,
        private readonly \DateTimeImmutable $failedAt,
    ) {
    }

    public function getMessageUuid(): string
    {
        return $this->messageUuid;
    }

    public function getExceptionClass(): string
    {
        return $this->exceptionClass;
    }

    public function getExceptionMessage(): string
    {
        return $this->exceptionMessage;
    }

    public function getErrorHash(): string
    {
        return $this->errorHash;
    }

    /**
     * @return array<string, array<bool|float|int|string|null>>
     */
    public function getErrorDetails(): array
    {
        return $this->errorDetails;
    }

    public function getStacktrace(): ?string
    {
        return $this->stacktrace;
    }

    public function getFailedAt(): \DateTimeImmutable
    {
        return $this->failedAt;
    }
}
