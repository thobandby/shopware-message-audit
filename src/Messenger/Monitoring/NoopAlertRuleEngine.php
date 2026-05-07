<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Monitoring;

use Thorsten\MessengerHistory\Messenger\Contract\AlertRuleEngineInterface;

final readonly class NoopAlertRuleEngine implements AlertRuleEngineInterface
{
    /**
     * @return list<\Thorsten\MessengerHistory\Messenger\Model\AlertRecord>
     */
    public function evaluate(): array
    {
        return [];
    }
}
