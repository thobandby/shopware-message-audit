<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Tests\Unit\Plugin;

use MessengerHistoryDashboard\Core\Repository\MessagePresentationFormatter;
use PHPUnit\Framework\TestCase;

final class MessagePresentationFormatterTest extends TestCase
{
    private MessagePresentationFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new MessagePresentationFormatter();
    }

    public function testFormatRowFormatsGermanProductExportMessengerEntry(): void
    {
        $row = $this->formatter->formatRow(
            [
                'message_class' => 'Shopware\\Core\\Content\\ProductExport\\ScheduledTask\\ProductExportGenerateTask',
                'source' => 'messenger',
                'subject_type' => null,
                'status' => 'dispatched',
                'retry_count' => 0,
            ],
            ['retry'],
            'Aktionen',
            'de-DE'
        );

        self::assertSame('Produkt-Export erzeugen', $row['message_name']);
        self::assertSame('Produkt-Export', $row['message_type']);
        self::assertSame('Integrationen', $row['topic_group']);
        self::assertSame(['retry'], $row['allowed_actions']);
        self::assertSame('Aktionen', $row['available_actions']);
        self::assertSame('Gesendet', $row['status_label']);
        self::assertSame('Erzeugt Exportdateien für Produktfeeds und externe Kanäle.', $row['business_summary']);
        self::assertSame('Unkritisch', $row['business_impact']);
        self::assertSame('Messenger', $row['source_label']);
        self::assertSame(0, $row['attempt_count']);
    }

    public function testFormatRowFormatsEnglishStateChangeEntry(): void
    {
        $row = $this->formatter->formatRow(
            [
                'message_class' => 'Shopware\\Checkout\\Order\\StatusTransition',
                'source' => 'state_change',
                'subject_type' => 'order',
                'status' => 'in_progress',
                'retry_count' => 4,
            ],
            [],
            'ignored',
            'en-GB'
        );

        self::assertSame('Order status: in_progress', $row['message_name']);
        self::assertSame('Order status', $row['message_type']);
        self::assertSame('Orders', $row['topic_group']);
        self::assertSame([], $row['allowed_actions']);
        self::assertSame('Details', $row['available_actions']);
        self::assertSame('In progress', $row['status_label']);
        self::assertSame('Documents synchronous status changes of an order.', $row['business_summary']);
        self::assertSame('Monitor', $row['business_impact']);
        self::assertSame('State change', $row['source_label']);
        self::assertSame(0, $row['attempt_count']);
    }

    public function testFormatRowFormatsScheduledTaskFallbackAndHumanizedName(): void
    {
        $row = $this->formatter->formatRow(
            [
                'message_class' => 'Shopware\\Elasticsearch\\Framework\\Indexing\\CreateAliasTask',
                'source' => 'messenger',
                'subject_type' => null,
                'status' => 'handled',
                'retry_count' => 1,
            ],
            ['view'],
            'Actions',
            'en-GB'
        );

        self::assertSame('Create Alias', $row['message_name']);
        self::assertSame('Miscellaneous', $row['message_type']);
        self::assertSame('System', $row['topic_group']);
        self::assertSame('Handled', $row['status_label']);
        self::assertSame('Processes general background tasks in the shop.', $row['business_summary']);
        self::assertSame('Low', $row['business_impact']);
        self::assertSame(2, $row['attempt_count']);
    }

    public function testFormatRowUsesSpecificCleanupTaskProfile(): void
    {
        $row = $this->formatter->formatRow(
            [
                'message_class' => 'MessengerHistoryDashboard\\Core\\ScheduledTask\\CleanupShopwareMessengerRetentionTask',
                'source' => 'messenger',
                'subject_type' => null,
                'status' => 'failed',
                'retry_count' => 0,
            ],
            ['retry'],
            'Actions',
            'en-GB'
        );

        self::assertSame('Messenger cleanup', $row['message_name']);
        self::assertSame('Scheduled task', $row['message_type']);
        self::assertSame('System', $row['topic_group']);
        self::assertSame('Automatically cleans up old Shopware Messenger entries.', $row['business_summary']);
        self::assertSame('Monitor', $row['business_impact']);
        self::assertSame('Failed', $row['status_label']);
        self::assertSame(1, $row['attempt_count']);
    }

    public function testFormatRowUsesReviewImpactForFailedPaypalMessage(): void
    {
        $row = $this->formatter->formatRow(
            [
                'message_class' => 'Swag\\PayPal\\Checkout\\Webhook\\SomeWebhookMessage',
                'source' => 'messenger',
                'subject_type' => null,
                'status' => 'failed',
                'retry_count' => 2,
            ],
            ['retry'],
            'Actions',
            'de-DE'
        );

        self::assertSame('PayPal', $row['message_type']);
        self::assertSame('Integrationen', $row['topic_group']);
        self::assertSame('Prüfen', $row['business_impact']);
        self::assertSame(3, $row['attempt_count']);
    }
}
