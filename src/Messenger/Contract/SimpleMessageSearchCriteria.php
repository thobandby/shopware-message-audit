<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Contract;

final readonly class SimpleMessageSearchCriteria implements MessageSearchCriteriaInterface
{
    public function __construct(
        private ?string $status = null,
        private ?string $transportName = null,
        private ?string $messageClass = null,
        private ?string $businessReference = null,
        private int $limit = 25,
        private int $offset = 0,
    ) {
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }
    public function getTransportName(): ?string
    {
        return $this->transportName;
    }
    public function getMessageClass(): ?string
    {
        return $this->messageClass;
    }
    public function getBusinessReference(): ?string
    {
        return $this->businessReference;
    }
    public function getLimit(): int
    {
        return $this->limit;
    }
    public function getOffset(): int
    {
        return $this->offset;
    }
}
