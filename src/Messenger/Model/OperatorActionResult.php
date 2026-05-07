<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Model;

final class OperatorActionResult
{
    /**
     * @param array<string, array<array-key, scalar|null>|scalar|null> $details
     */
    public function __construct(
        private readonly string $actionUuid,
        private readonly string $result,
        private readonly string $messageUuid,
        private readonly ?string $newStatus,
        private readonly array $details = [],
    ) {
    }

    public function getActionUuid(): string
    {
        return $this->actionUuid;
    }

    public function getResult(): string
    {
        return $this->result;
    }

    public function getMessageUuid(): string
    {
        return $this->messageUuid;
    }

    public function getNewStatus(): ?string
    {
        return $this->newStatus;
    }

    /**
     * @return array<string, array<array-key, scalar|null>|scalar|null>
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
