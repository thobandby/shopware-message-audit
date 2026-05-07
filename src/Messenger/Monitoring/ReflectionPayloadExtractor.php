<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Monitoring;

use Symfony\Component\Messenger\Envelope;
use Thorsten\MessengerHistory\Messenger\Contract\MessagePayloadExtractorInterface;

final readonly class ReflectionPayloadExtractor implements MessagePayloadExtractorInterface
{
    /**
     * @return array<string, array<array-key, scalar|null>|scalar|null>
     */
    public function extractPayload(Envelope $envelope): array
    {
        return get_object_vars($envelope->getMessage());
    }

    /**
     * @return array<string, array<array-key, scalar|null>|scalar|null>
     */
    public function extractPayloadPreview(Envelope $envelope): array
    {
        return $this->extractPayload($envelope);
    }

    /**
     * @return array<string, array<array-key, scalar|null>|scalar|null>
     */
    public function extractHeadersPreview(Envelope $envelope): array
    {
        return ['message_class' => $envelope->getMessage()::class];
    }
}
