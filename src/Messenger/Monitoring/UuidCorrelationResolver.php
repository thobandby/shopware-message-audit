<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Monitoring;

use Symfony\Component\Messenger\Envelope;
use Thorsten\MessengerHistory\Messenger\Contract\MessageCorrelationResolverInterface;
use Thorsten\MessengerHistory\Messenger\Stamp\CausationIdStamp;
use Thorsten\MessengerHistory\Messenger\Stamp\CorrelationIdStamp;
use Thorsten\MessengerHistory\Messenger\Stamp\MessageUuidStamp;

final readonly class UuidCorrelationResolver implements MessageCorrelationResolverInterface
{
    public function resolveMessageUuid(Envelope $envelope): string
    {
        $stamp = $envelope->last(MessageUuidStamp::class);
        return $stamp instanceof MessageUuidStamp ? $stamp->messageUuid : $this->newUuid();
    }

    public function resolveCorrelationId(Envelope $envelope): ?string
    {
        $stamp = $envelope->last(CorrelationIdStamp::class);
        return $stamp instanceof CorrelationIdStamp ? $stamp->correlationId : null;
    }

    public function resolveCausationId(Envelope $envelope): ?string
    {
        $stamp = $envelope->last(CausationIdStamp::class);
        return $stamp instanceof CausationIdStamp ? $stamp->causationId : null;
    }

    private function newUuid(): string
    {
        return \sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );
    }
}
