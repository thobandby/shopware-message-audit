<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Tests\Unit\Plugin;

use MessengerHistoryDashboard\Core\Message\SampleFailureMessage;
use MessengerHistoryDashboard\Core\Message\SampleSuccessMessage;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

final class DemoMessageRoutingTest extends TestCase
{
    public function testSampleSuccessMessageIsAsync(): void
    {
        self::assertInstanceOf(
            AsyncMessageInterface::class,
            new SampleSuccessMessage('demo-success-1', 'Demo success')
        );
    }

    public function testSampleFailureMessageIsAsync(): void
    {
        self::assertInstanceOf(
            AsyncMessageInterface::class,
            new SampleFailureMessage('demo-failure-1', 'Demo failure')
        );
    }
}
