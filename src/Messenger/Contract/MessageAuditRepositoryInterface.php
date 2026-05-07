<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Contract;

use Thorsten\MessengerHistory\Messenger\Model\FailureSnapshot;
use Thorsten\MessengerHistory\Messenger\Model\MessageSnapshot;
use Thorsten\MessengerHistory\Messenger\Model\TransitionRecord;

interface MessageAuditRepositoryInterface
{
    public function upsertMessage(MessageSnapshot $messageSnapshot): void;

    public function appendTransition(TransitionRecord $transitionRecord): void;

    public function appendFailure(FailureSnapshot $failureSnapshot): void;

    public function findByMessageUuid(string $messageUuid): ?MessageSnapshot;

    /**
     * @return list<MessageSnapshot>
     */
    public function search(MessageSearchCriteriaInterface $criteria): array;
}
