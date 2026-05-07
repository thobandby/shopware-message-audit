<?php declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Message;

final class SampleSuccessMessage
{
    public function __construct(
        public readonly string $reference,
        public readonly string $text
    ) {
    }

    public function toArray(): array
    {
        return ['reference' => $this->reference, 'text' => $this->text];
    }
}
