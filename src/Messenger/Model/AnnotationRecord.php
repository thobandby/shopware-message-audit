<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Model;

final readonly class AnnotationRecord
{
    public function __construct(
        public string $messageUuid,
        public ?OperatorIdentity $author,
        public string $category,
        public string $note,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
