<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Repository;

use Thorsten\MessengerHistory\Messenger\Contract\MessageAuditRepositoryInterface;
use Thorsten\MessengerHistory\Messenger\Contract\MessageSearchCriteriaInterface;
use Thorsten\MessengerHistory\Messenger\Model\FailureSnapshot;
use Thorsten\MessengerHistory\Messenger\Model\MessageSnapshot;
use Thorsten\MessengerHistory\Messenger\Model\TransitionRecord;

final class InMemoryMessageAuditRepository implements MessageAuditRepositoryInterface
{
    /** @var array<string, MessageSnapshot> */
    private array $messages = [];

    /** @var list<TransitionRecord> */
    private array $transitions = [];

    /** @var list<FailureSnapshot> */
    private array $failures = [];

    public function upsertMessage(MessageSnapshot $messageSnapshot): void
    {
        $this->messages[$messageSnapshot->messageUuid] = $messageSnapshot;
    }

    public function appendTransition(TransitionRecord $transitionRecord): void
    {
        $this->transitions[] = $transitionRecord;
    }

    public function appendFailure(FailureSnapshot $failureSnapshot): void
    {
        $this->failures[] = $failureSnapshot;
    }

    public function findByMessageUuid(string $messageUuid): ?MessageSnapshot
    {
        return $this->messages[$messageUuid] ?? null;
    }

    /**
     * @return list<MessageSnapshot>
     */
    public function search(MessageSearchCriteriaInterface $criteria): array
    {
        $messages = array_values(array_filter(
            $this->messages,
            fn (MessageSnapshot $snapshot): bool => $this->matchesCriteria($snapshot, $criteria)
        ));

        return \array_slice($messages, $criteria->getOffset(), $criteria->getLimit());
    }

    private function matchesCriteria(MessageSnapshot $snapshot, MessageSearchCriteriaInterface $criteria): bool
    {
        return $this->matchesValue($snapshot->status, $criteria->getStatus())
            && $this->matchesValue($snapshot->transportName, $criteria->getTransportName())
            && $this->matchesValue($snapshot->messageClass, $criteria->getMessageClass())
            && $this->matchesValue($snapshot->businessReference, $criteria->getBusinessReference());
    }

    private function matchesValue(?string $actual, ?string $expected): bool
    {
        return $expected === null || $actual === $expected;
    }
}
