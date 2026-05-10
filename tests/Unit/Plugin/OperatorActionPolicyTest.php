<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Tests\Unit\Plugin;

use MessengerHistoryDashboard\Core\Operator\OperatorActionPolicy;
use PHPUnit\Framework\TestCase;

final class OperatorActionPolicyTest extends TestCase
{
    private OperatorActionPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new OperatorActionPolicy();
    }

    public function testFailedMessagesAllowAllOperatorActions(): void
    {
        self::assertSame(['retry', 'quarantine', 'dismiss'], $this->policy->allowedActions('failed'));
        self::assertTrue($this->policy->isAllowed('retry', 'failed'));
        self::assertTrue($this->policy->isAllowed('quarantine', 'failed'));
        self::assertTrue($this->policy->isAllowed('dismiss', 'failed'));
    }

    public function testHandledMessagesOnlyAllowDismiss(): void
    {
        self::assertSame(['dismiss'], $this->policy->allowedActions('handled'));
        self::assertFalse($this->policy->isAllowed('retry', 'handled'));
        self::assertFalse($this->policy->isAllowed('quarantine', 'handled'));
        self::assertTrue($this->policy->isAllowed('dismiss', 'handled'));
    }

    public function testUnknownStatusAllowsNoOperatorAction(): void
    {
        self::assertSame([], $this->policy->allowedActions('unknown'));
        self::assertSame('Details', $this->policy->formatAllowedActions('unknown'));
    }
}
