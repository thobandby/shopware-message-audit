<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Contract;

use Symfony\Component\Messenger\Envelope;

interface MessagePayloadExtractorInterface
{
    /**
     * @return array<string, array<array-key, scalar|null>|scalar|null>
     */
    public function extractPayload(Envelope $envelope): array;

    /**
     * @return array<string, array<array-key, scalar|null>|scalar|null>
     */
    public function extractPayloadPreview(Envelope $envelope): array;

    /**
     * @return array<string, array<array-key, scalar|null>|scalar|null>
     */
    public function extractHeadersPreview(Envelope $envelope): array;
}
