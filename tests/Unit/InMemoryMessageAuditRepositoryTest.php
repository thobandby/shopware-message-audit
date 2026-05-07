<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Thorsten\MessengerHistory\Messenger\Contract\SimpleMessageSearchCriteria;
use Thorsten\MessengerHistory\Messenger\Model\MessageSnapshot;
use Thorsten\MessengerHistory\Messenger\Repository\InMemoryMessageAuditRepository;

final class InMemoryMessageAuditRepositoryTest extends TestCase
{
    private InMemoryMessageAuditRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryMessageAuditRepository();
    }

    public function testUpsertAndFindByMessageUuid(): void
    {
        $message1 = $this->createMessageSnapshot('uuid-1', 'failed');
        $message2 = $this->createMessageSnapshot('uuid-2', 'succeeded');

        $this->repository->upsertMessage($message1);
        $this->repository->upsertMessage($message2);

        self::assertEquals($message1, $this->repository->findByMessageUuid('uuid-1'));
        self::assertEquals($message2, $this->repository->findByMessageUuid('uuid-2'));
        self::assertNull($this->repository->findByMessageUuid('uuid-3'));
    }

    public function testUpsertMessageUpdatesExistingMessage(): void
    {
        $message1 = $this->createMessageSnapshot('uuid-1', 'failed');
        $this->repository->upsertMessage($message1);

        $updatedMessage1 = $this->createMessageSnapshot('uuid-1', 'succeeded');
        $this->repository->upsertMessage($updatedMessage1);

        self::assertEquals($updatedMessage1, $this->repository->findByMessageUuid('uuid-1'));
    }

    public function testSearchFiltersMessagesByStatus(): void
    {
        $this->storeMessages(
            $this->createMessageSnapshot('uuid-1', 'failed'),
            $this->createMessageSnapshot('uuid-2', 'succeeded'),
        );

        $foundMessages = $this->repository->search(new SimpleMessageSearchCriteria(status: 'failed'));

        self::assertCount(1, $foundMessages);
        self::assertSame('uuid-1', $foundMessages[0]->messageUuid);
    }

    private function storeMessages(MessageSnapshot ...$messages): void
    {
        foreach ($messages as $message) {
            $this->repository->upsertMessage($message);
        }
    }

    private function createMessageSnapshot(string $uuid, string $status): MessageSnapshot
    {
        $now = new \DateTimeImmutable();

        return new MessageSnapshot(
            messageUuid: $uuid,
            messageClass: 'TestMessageClass',
            messageName: 'test.message',
            busName: 'command_bus',
            transportName: 'async',
            transportMessageId: '123',
            correlationId: 'corr-123',
            causationId: 'caus-123',
            businessType: 'order',
            businessReference: 'ref-123',
            status: $status,
            retryCount: 0,
            isQuarantined: false,
            payload: [],
            payloadPreview: [],
            headersPreview: [],
            firstSeenAt: $now,
            lastSeenAt: $now,
            firstHandledAt: null,
            finishedAt: null,
        );
    }
}
