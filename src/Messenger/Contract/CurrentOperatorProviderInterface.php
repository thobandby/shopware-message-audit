<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Contract;

use Thorsten\MessengerHistory\Messenger\Model\OperatorIdentity;

interface CurrentOperatorProviderInterface
{
    public function getCurrentOperator(): ?OperatorIdentity;
}
