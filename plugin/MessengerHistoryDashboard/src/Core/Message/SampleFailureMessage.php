<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

final class SampleFailureMessage implements AsyncMessageInterface
{
    public function __construct(
        public readonly string $reference,
        public readonly string $text
    ) {
    }

    /**
     * @return array{reference:string, text:string}
     */
    public function toArray(): array
    {
        return ['reference' => $this->reference, 'text' => $this->text];
    }
}
