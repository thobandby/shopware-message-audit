<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Contract;

use Thorsten\MessengerHistory\Messenger\Model\MessageSnapshot;
use Thorsten\MessengerHistory\Messenger\Model\OperatorActionRequest;

interface OperatorPolicyInterface
{
    public function canExecute(OperatorActionRequest $request, MessageSnapshot $messageSnapshot): PolicyDecisionInterface;
}
