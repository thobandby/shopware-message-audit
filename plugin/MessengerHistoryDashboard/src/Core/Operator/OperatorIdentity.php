<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Operator;

final class OperatorIdentity
{
    public function __construct(
        private readonly ?string $operatorId,
        private readonly string $operatorType,
        private readonly string $operatorLabel,
        private readonly ?string $operatorEmail = null
    ) {
    }

    public function getOperatorId(): ?string
    {
        return $this->operatorId;
    }

    public function getOperatorType(): string
    {
        return $this->operatorType;
    }

    public function getOperatorLabel(): string
    {
        return $this->operatorLabel;
    }

    public function getOperatorEmail(): ?string
    {
        return $this->operatorEmail;
    }
}
