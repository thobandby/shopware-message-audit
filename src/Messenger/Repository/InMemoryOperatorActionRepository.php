<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Repository;

use Thorsten\MessengerHistory\Messenger\Contract\OperatorActionRepositoryInterface;
use Thorsten\MessengerHistory\Messenger\Model\OperatorActionRecord;

final class InMemoryOperatorActionRepository implements OperatorActionRepositoryInterface
{
    /** @var list<OperatorActionRecord> */
    private array $records = [];

    public function append(OperatorActionRecord $record): void
    {
        $this->records[] = $record;
    }

    /**
     * @return list<OperatorActionRecord>
     */
    public function findByMessageUuid(string $messageUuid): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (OperatorActionRecord $record): bool => $record->getMessageUuid() === $messageUuid,
        ));
    }
}
