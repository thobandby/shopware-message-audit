<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Contract;

use Symfony\Component\Messenger\Envelope;

interface MessageCorrelationResolverInterface
{
    public function resolveMessageUuid(Envelope $envelope): string;

    public function resolveCorrelationId(Envelope $envelope): ?string;

    public function resolveCausationId(Envelope $envelope): ?string;
}
