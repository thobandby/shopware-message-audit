<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Contract;

use Thorsten\MessengerHistory\Messenger\Model\AnnotationRecord;

interface AnnotationRepositoryInterface
{
    public function add(AnnotationRecord $record): void;

    /**
     * @return list<AnnotationRecord>
     */
    public function findByMessageUuid(string $messageUuid): array;
}
