<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Policy;

use Thorsten\MessengerHistory\Messenger\Contract\OperatorPolicyInterface;
use Thorsten\MessengerHistory\Messenger\Contract\PolicyDecisionInterface;
use Thorsten\MessengerHistory\Messenger\Model\MessageSnapshot;
use Thorsten\MessengerHistory\Messenger\Model\OperatorActionRequest;

final readonly class AllowAllOperatorPolicy implements OperatorPolicyInterface
{
    public function canExecute(OperatorActionRequest $request, MessageSnapshot $messageSnapshot): PolicyDecisionInterface
    {
        unset($request, $messageSnapshot);

        return new AllowAllPolicyDecision();
    }
}
