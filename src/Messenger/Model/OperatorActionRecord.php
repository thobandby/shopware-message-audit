<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Model;

final class OperatorActionRecord
{
    /**
     * @param array<string, array<bool|float|int|string|null>> $resultDetails
     * @param array<string, array<bool|float|int|string|null>>|null $overridePayload
     */
    public function __construct(
        private readonly string $actionUuid,
        private readonly string $messageUuid,
        private readonly string $actionType,
        private readonly ?string $oldStatus,
        private readonly ?string $newStatus,
        private readonly ?string $oldTransport,
        private readonly ?string $newTransport,
        private readonly ?OperatorIdentity $operator,
        private readonly string $reasonCode,
        private readonly ?string $reasonText,
        private readonly ?string $requestId,
        private readonly ?string $uiSessionId,
        private readonly ?array $overridePayload,
        private readonly string $result,
        private readonly array $resultDetails,
        private readonly ?string $approvedByUserId,
        private readonly ?\DateTimeImmutable $approvedAt,
        private readonly \DateTimeImmutable $executedAt,
    ) {
    }

    public function getActionUuid(): string
    {
        return $this->actionUuid;
    }

    public function getMessageUuid(): string
    {
        return $this->messageUuid;
    }

    public function getActionType(): string
    {
        return $this->actionType;
    }

    public function getOldStatus(): ?string
    {
        return $this->oldStatus;
    }

    public function getNewStatus(): ?string
    {
        return $this->newStatus;
    }

    public function getOldTransport(): ?string
    {
        return $this->oldTransport;
    }

    public function getNewTransport(): ?string
    {
        return $this->newTransport;
    }

    public function getOperator(): ?OperatorIdentity
    {
        return $this->operator;
    }

    public function getReasonCode(): string
    {
        return $this->reasonCode;
    }

    public function getReasonText(): ?string
    {
        return $this->reasonText;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function getUiSessionId(): ?string
    {
        return $this->uiSessionId;
    }

    /**
     * @return array<string, array<bool|float|int|string|null>>|null
     */
    public function getOverridePayload(): ?array
    {
        return $this->overridePayload;
    }

    public function getResult(): string
    {
        return $this->result;
    }

    /**
     * @return array<string, array<bool|float|int|string|null>>
     */
    public function getResultDetails(): array
    {
        return $this->resultDetails;
    }

    public function getApprovedByUserId(): ?string
    {
        return $this->approvedByUserId;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function getExecutedAt(): \DateTimeImmutable
    {
        return $this->executedAt;
    }
}
