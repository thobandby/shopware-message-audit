<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Thorsten\MessengerHistory\Messenger\Contract\PolicyDecisionInterface;
use Thorsten\MessengerHistory\Messenger\Model\MessageSnapshot;
use Thorsten\MessengerHistory\Messenger\Model\OperatorActionRequest;
use Thorsten\MessengerHistory\Messenger\Policy\AllowAllOperatorPolicy;
use Thorsten\MessengerHistory\Messenger\Policy\AllowAllPolicyDecision;

final class AllowAllPolicyTest extends TestCase
{
    public function testAllowAllPolicyDecisionIsAllowed(): void
    {
        $decision = new AllowAllPolicyDecision();
        self::assertTrue($decision->isAllowed());
        self::assertNull($decision->getReason());
    }

    public function testAllowAllOperatorPolicyReturnsAllowAllDecision(): void
    {
        $policy = new AllowAllOperatorPolicy();

        $messageUuid = 'test-message-uuid';
        $request = new OperatorActionRequest(
            messageUuid: $messageUuid,
            actionType: 'retry_now',
            reasonCode: 'test-reason',
            reasonText: null,
            expectedStatus: null,
            operator: null
        );
        $snapshot = new MessageSnapshot(
            messageUuid: $messageUuid,
            messageClass: 'TestMessageClass',
            messageName: 'test.message',
            busName: 'command_bus',
            transportName: 'async',
            transportMessageId: '123',
            correlationId: 'corr-123',
            causationId: 'caus-123',
            businessType: 'order',
            businessReference: 'ref-123',
            status: 'failed',
            retryCount: 0,
            isQuarantined: false,
            payload: [],
            payloadPreview: [],
            headersPreview: [],
            firstSeenAt: new \DateTimeImmutable(),
            lastSeenAt: new \DateTimeImmutable(),
            firstHandledAt: null,
            finishedAt: null,
        );

        $decision = $policy->canExecute($request, $snapshot);

        self::assertInstanceOf(AllowAllPolicyDecision::class, $decision);
        self::assertInstanceOf(PolicyDecisionInterface::class, $decision);
        self::assertTrue($decision->isAllowed());
        self::assertNull($decision->getReason());
    }
}
