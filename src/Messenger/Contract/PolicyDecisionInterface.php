<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Contract;

interface PolicyDecisionInterface
{
    public function isAllowed(): bool;

    public function getReason(): ?string;
}
