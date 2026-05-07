<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Policy;

use Thorsten\MessengerHistory\Messenger\Contract\PolicyDecisionInterface;

final readonly class AllowAllPolicyDecision implements PolicyDecisionInterface
{
    public function isAllowed(): bool
    {
        return true;
    }

    public function getReason(): ?string
    {
        return null;
    }
}
