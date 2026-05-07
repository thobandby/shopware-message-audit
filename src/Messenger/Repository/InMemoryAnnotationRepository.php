<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Repository;

use Thorsten\MessengerHistory\Messenger\Contract\AnnotationRepositoryInterface;
use Thorsten\MessengerHistory\Messenger\Model\AnnotationRecord;

final class InMemoryAnnotationRepository implements AnnotationRepositoryInterface
{
    /** @var list<AnnotationRecord> */
    private array $records = [];

    public function add(AnnotationRecord $record): void
    {
        $this->records[] = $record;
    }

    /**
     * @return list<AnnotationRecord>
     */
    public function findByMessageUuid(string $messageUuid): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (AnnotationRecord $record): bool => $record->messageUuid === $messageUuid,
        ));
    }
}
