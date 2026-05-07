<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Monitoring;

use Thorsten\MessengerHistory\Messenger\Contract\CurrentOperatorProviderInterface;
use Thorsten\MessengerHistory\Messenger\Model\OperatorIdentity;

final readonly class StaticCurrentOperatorProvider implements CurrentOperatorProviderInterface
{
    public function getCurrentOperator(): ?OperatorIdentity
    {
        return new OperatorIdentity(null, 'system@localhost', 'System');
    }
}
