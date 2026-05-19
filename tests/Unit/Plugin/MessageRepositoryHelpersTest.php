<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Tests\Unit\Plugin;

use MessengerHistoryDashboard\Core\Repository\MessageListCriteria;
use MessengerHistoryDashboard\Core\Repository\MessagePresentationFormatter;
use MessengerHistoryDashboard\Core\Repository\MessageRowGrouper;
use MessengerHistoryDashboard\Core\Repository\MessageWhereClauseBuilder;
use PHPUnit\Framework\TestCase;

final class MessageRepositoryHelpersTest extends TestCase
{
    private MessageWhereClauseBuilder $whereClauseBuilder;

    private MessageRowGrouper $rowGrouper;

    protected function setUp(): void
    {
        $this->whereClauseBuilder = new MessageWhereClauseBuilder();
        $this->rowGrouper = new MessageRowGrouper(new MessagePresentationFormatter());
    }

    public function testCreateUtc24HourCutoffReturnsUtcTimestampString(): void
    {
        $cutoff = $this->whereClauseBuilder->createUtc24HourCutoff();
        $cutoffDate = \DateTimeImmutable::createFromFormat(
            MessageWhereClauseBuilder::DATETIME_FORMAT,
            $cutoff,
            new \DateTimeZone('UTC')
        );

        self::assertInstanceOf(\DateTimeImmutable::class, $cutoffDate);

        $secondsDifference = abs(
            $cutoffDate->getTimestamp() - (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->sub(new \DateInterval('P1D'))
                ->getTimestamp()
        );

        self::assertLessThanOrEqual(5, $secondsDifference);
    }

    public function testBuildWhereClauseReturnsEmptyClauseForEmptyCriteria(): void
    {
        $whereClause = $this->whereClauseBuilder->buildWhereClause(new MessageListCriteria());

        self::assertSame('', $whereClause['sql']);
        self::assertSame([], $whereClause['parameters']);
    }

    public function testBuildWhereClauseBuildsCompositeFiltersAndParameters(): void
    {
        $criteria = new MessageListCriteria(
            status: 'failed',
            query: 'cache',
            entryFilter: 'shopware_messenger',
            topicGroup: 'Integrationen',
            createdFrom: new \DateTimeImmutable('2026-05-18 10:00:00', new \DateTimeZone('UTC')),
            createdTo: new \DateTimeImmutable('2026-05-18 12:00:00', new \DateTimeZone('UTC')),
            messageClass: 'ProductExport',
            transportName: 'scheduler',
            businessReference: '10012'
        );

        $whereClause = $this->whereClauseBuilder->buildWhereClause($criteria);

        self::assertStringContainsString("m.status = :status OR (m.source = 'state_change'", $whereClause['sql']);
        self::assertStringContainsString('m.created_at >= :createdFrom', $whereClause['sql']);
        self::assertStringContainsString('m.created_at <= :createdTo', $whereClause['sql']);
        self::assertStringContainsString('m.id LIKE :query OR m.message_class LIKE :query', $whereClause['sql']);
        self::assertStringContainsString('m.message_class LIKE :messageClass', $whereClause['sql']);
        self::assertStringContainsString('m.transport_name LIKE :transportName', $whereClause['sql']);
        self::assertStringContainsString('m.business_reference LIKE :businessReference', $whereClause['sql']);
        self::assertStringContainsString("m.source = 'messenger' AND SUBSTR(m.message_class, 1, :entryFilterShopwarePrefixLength) = :entryFilterShopwarePrefix", $whereClause['sql']);
        self::assertStringContainsString("m.message_class LIKE '%\\\\Framework\\\\Webhook\\\\%'", $whereClause['sql']);

        self::assertSame('failed', $whereClause['parameters']['status']);
        self::assertSame('2026-05-18 10:00:00', $whereClause['parameters']['createdFrom']);
        self::assertSame('2026-05-18 12:00:00', $whereClause['parameters']['createdTo']);
        self::assertSame('%cache%', $whereClause['parameters']['query']);
        self::assertSame('%ProductExport%', $whereClause['parameters']['messageClass']);
        self::assertSame('%scheduler%', $whereClause['parameters']['transportName']);
        self::assertSame('%10012%', $whereClause['parameters']['businessReference']);
        self::assertSame(MessageWhereClauseBuilder::SHOPWARE_CLASS_PREFIX, $whereClause['parameters']['entryFilterShopwarePrefix']);
        self::assertSame(
            \strlen(MessageWhereClauseBuilder::SHOPWARE_CLASS_PREFIX),
            $whereClause['parameters']['entryFilterShopwarePrefixLength']
        );
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $whereClause['parameters']['entryFilterCutoff']);
    }

    /**
     * @dataProvider provideEntryAndTopicFilters
     */
    public function testBuildWhereClauseHandlesSupportedEntryAndTopicFilters(
        MessageListCriteria $criteria,
        string $expectedSqlFragment,
        array $expectedParameters
    ): void {
        $whereClause = $this->whereClauseBuilder->buildWhereClause($criteria);

        self::assertStringContainsString($expectedSqlFragment, $whereClause['sql']);

        foreach ($expectedParameters as $parameterName => $expectedValue) {
            self::assertArrayHasKey($parameterName, $whereClause['parameters']);
            self::assertSame($expectedValue, $whereClause['parameters'][$parameterName]);
        }
    }

    public function testBuildWhereClauseIgnoresUnknownEntryAndTopicFilters(): void
    {
        $criteria = new MessageListCriteria(
            entryFilter: 'unknown_filter',
            topicGroup: 'Unbekannt'
        );

        $whereClause = $this->whereClauseBuilder->buildWhereClause($criteria);

        self::assertSame('', $whereClause['sql']);
        self::assertSame([], $whereClause['parameters']);
    }

    public function testGroupRowsGroupsStateChangesByBusinessReferenceAndKeepsMostRelevantRow(): void
    {
        $rows = [
            [
                'id' => 'transaction-paid',
                'source' => 'state_change',
                'subject_type' => 'order_transaction',
                'subject_id' => 'tx-1',
                'business_reference' => '10012',
                'status' => 'paid',
                'created_at' => '2026-05-18 11:01:42',
                'message_class' => 'Shopware\\Checkout\\Payment\\PaymentMessage',
            ],
            [
                'id' => 'order-in-progress',
                'source' => 'state_change',
                'subject_type' => 'order',
                'subject_id' => 'order-1',
                'business_reference' => '10012',
                'status' => 'in_progress',
                'created_at' => '2026-05-18 11:01:46',
                'message_class' => 'Shopware\\Checkout\\Order\\OrderMessage',
            ],
            [
                'id' => 'order-completed',
                'source' => 'state_change',
                'subject_type' => 'order',
                'subject_id' => 'order-1',
                'business_reference' => '10012',
                'status' => 'completed',
                'created_at' => '2026-05-18 11:01:46',
                'message_class' => 'Shopware\\Checkout\\Order\\OrderMessage',
            ],
        ];

        $groupedRows = $this->rowGrouper->groupRows($rows, 'de-DE');

        self::assertCount(1, $groupedRows);
        self::assertSame('order-completed', $groupedRows[0]['id']);
        self::assertSame(3, $groupedRows[0]['group_count']);
        self::assertSame('Bestellung 10012', $groupedRows[0]['message_name']);
        self::assertSame('Bestellung', $groupedRows[0]['message_type']);
        self::assertSame('Bestellungen', $groupedRows[0]['topic_group']);
    }

    public function testGroupRowsGroupsMessengerEntriesByClassAndMinute(): void
    {
        $rows = [
            [
                'id' => 'product-export-dispatched',
                'source' => 'messenger',
                'subject_type' => '',
                'business_reference' => '',
                'status' => 'dispatched',
                'created_at' => '2026-05-18 11:12:49',
                'message_class' => 'Shopware\\Core\\Content\\ProductExport\\ScheduledTask\\ProductExportGenerateTask',
            ],
            [
                'id' => 'product-export-handled',
                'source' => 'messenger',
                'subject_type' => '',
                'business_reference' => '',
                'status' => 'handled',
                'created_at' => '2026-05-18 11:12:26',
                'message_class' => 'Shopware\\Core\\Content\\ProductExport\\ScheduledTask\\ProductExportGenerateTask',
            ],
            [
                'id' => 'product-export-received',
                'source' => 'messenger',
                'subject_type' => '',
                'business_reference' => '',
                'status' => 'received',
                'created_at' => '2026-05-18 11:12:26',
                'message_class' => 'Shopware\\Core\\Content\\ProductExport\\ScheduledTask\\ProductExportGenerateTask',
            ],
        ];

        $groupedRows = $this->rowGrouper->groupRows($rows, 'de-DE');

        self::assertCount(1, $groupedRows);
        self::assertSame('product-export-dispatched', $groupedRows[0]['id']);
        self::assertSame(3, $groupedRows[0]['group_count']);
        self::assertSame('Details', $groupedRows[0]['available_actions']);
    }

    public function testGroupRowsLeavesUnmatchedMessengerAndBusinessRowsUngrouped(): void
    {
        $rows = [
            [
                'id' => 'single-messenger',
                'source' => 'messenger',
                'subject_type' => '',
                'business_reference' => '',
                'status' => 'received',
                'created_at' => 'invalid-date',
                'message_class' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\InvalidateCacheTask',
            ],
            [
                'id' => 'business-row',
                'source' => 'messenger',
                'subject_type' => '',
                'business_reference' => '10077',
                'status' => 'received',
                'created_at' => '2026-05-18 11:13:26',
                'message_class' => 'Shopware\\Core\\Framework\\Adapter\\Cache\\InvalidateCacheTask',
            ],
        ];

        $groupedRows = $this->rowGrouper->groupRows($rows, 'de-DE');

        self::assertCount(2, $groupedRows);
        self::assertSame('single-messenger', $groupedRows[0]['id']);
        self::assertSame('business-row', $groupedRows[1]['id']);
        self::assertArrayNotHasKey('group_count', $groupedRows[0]);
        self::assertArrayNotHasKey('group_count', $groupedRows[1]);
    }

    /**
     * @return iterable<string, array{0: MessageListCriteria, 1: string, 2: array<string, int|string>}>
     */
    public static function provideEntryAndTopicFilters(): iterable
    {
        yield 'internal_jobs' => [
            new MessageListCriteria(entryFilter: 'internal_jobs'),
            "SUBSTR(m.message_class, 1, :entryFilterShopwarePrefixLength) <> :entryFilterShopwarePrefix",
            [
                'entryFilterShopwarePrefix' => MessageWhereClauseBuilder::SHOPWARE_CLASS_PREFIX,
                'entryFilterShopwarePrefixLength' => \strlen(MessageWhereClauseBuilder::SHOPWARE_CLASS_PREFIX),
            ],
        ];

        yield 'orders' => [
            new MessageListCriteria(entryFilter: 'orders'),
            "(m.subject_type IN ('order', 'order_delivery') OR m.message_class LIKE '%\\\\Checkout\\\\Order\\\\%')",
            [],
        ];

        yield 'payments' => [
            new MessageListCriteria(entryFilter: 'payments'),
            "(m.subject_type = 'order_transaction' OR m.message_class LIKE '%\\\\Checkout\\\\Payment\\\\%' OR m.message_class LIKE 'Swag\\\\PayPal\\\\%')",
            [],
        ];

        yield 'topic_group_system' => [
            new MessageListCriteria(topicGroup: 'System'),
            "m.message_class NOT LIKE '%\\\\Checkout\\\\Order\\\\%'",
            [],
        ];
    }
}
