<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Model;

final readonly class OperatorActionRequest
{
    public function __construct(
        public string $messageUuid,
        public string $actionType,
        public string $reasonCode,
        public ?string $reasonText,
        public ?string $expectedStatus,
        public ?OperatorIdentity $operator,
    ) {
    }
}
