<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Model;

final readonly class OperatorIdentity
{
    public function __construct(
        public ?string $userId,
        public ?string $email,
        public ?string $displayName,
    ) {
    }
}
