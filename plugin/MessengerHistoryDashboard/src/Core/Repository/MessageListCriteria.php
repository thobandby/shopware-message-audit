<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Repository;

final readonly class MessageListCriteria
{
    public function __construct(
        public ?string $status = null,
        public ?string $query = null,
        public int $page = 1,
        public int $limit = 25,
        public ?string $entryFilter = null,
        public ?string $topicGroup = null,
        public ?\DateTimeImmutable $createdFrom = null,
        public ?\DateTimeImmutable $createdTo = null,
        public ?string $messageClass = null,
        public ?string $transportName = null,
        public ?string $businessReference = null
    ) {
    }
}
