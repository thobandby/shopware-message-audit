<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Contract;

use Thorsten\MessengerHistory\Messenger\Model\AlertRecord;

interface AlertRuleEngineInterface
{
    /**
     * @return list<AlertRecord>
     */
    public function evaluate(): array;
}
