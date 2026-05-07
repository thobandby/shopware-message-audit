<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Contract;

use Thorsten\MessengerHistory\Messenger\Model\OperatorActionRecord;

interface OperatorActionRepositoryInterface
{
    public function append(OperatorActionRecord $record): void;

    /**
     * @return list<OperatorActionRecord>
     */
    public function findByMessageUuid(string $messageUuid): array;
}
