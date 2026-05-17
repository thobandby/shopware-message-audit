<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Repository;

final class MessagePresentationFormatter
{
    private const TYPE_ORDER_STATUS = 'type.order_status';
    private const TYPE_PAYMENT_STATUS = 'type.payment_status';
    private const TYPE_DELIVERY_STATUS = 'type.delivery_status';
    private const TYPE_STATE_CHANGE = 'type.state_change';
    private const TYPE_ORDER = 'type.order';
    private const TYPE_PAYMENT = 'type.payment';
    private const TYPE_INDEXER = 'type.indexer';
    private const TYPE_FLOW = 'type.flow';
    private const TYPE_MAIL = 'type.mail';
    private const TYPE_WEBHOOK = 'type.webhook';
    private const TYPE_MEDIA = 'type.media';
    private const TYPE_PRODUCT_EXPORT = 'type.product_export';
    private const TYPE_SITEMAP = 'type.sitemap';
    private const TYPE_SCHEDULED_TASK = 'type.scheduled_task';
    private const TYPE_PLUGIN = 'type.plugin';
    private const TYPE_PAYPAL = 'type.paypal';
    private const TYPE_MISC = 'type.misc';

    private const GROUP_ORDERS = 'group.orders';
    private const GROUP_PAYMENTS = 'group.payments';
    private const GROUP_COMMUNICATION = 'group.communication';
    private const GROUP_INTEGRATIONS = 'group.integrations';
    private const GROUP_CONTENT = 'group.content';
    private const GROUP_SYSTEM = 'group.system';

    private const LABEL_DETAILS = 'label.details';
    private const LABEL_MESSENGER = 'label.messenger';
    private const LABEL_STATE_CHANGE = 'label.state_change';
    private const LABEL_REVIEW = 'label.review';
    private const LABEL_MONITOR = 'label.monitor';
    private const LABEL_LOW = 'label.low';

    private const NAME_GENERATE_PRODUCT_EXPORT = 'name.generate_product_export';
    private const SUMMARY_ORDER_STATUS = 'summary.order_status';
    private const SUMMARY_PAYMENT_STATUS = 'summary.payment_status';
    private const SUMMARY_DELIVERY_STATUS = 'summary.delivery_status';
    private const SUMMARY_STATE_CHANGE = 'summary.state_change';
    private const SUMMARY_ORDER = 'summary.order';
    private const SUMMARY_PAYMENT = 'summary.payment';
    private const SUMMARY_INDEXER = 'summary.indexer';
    private const SUMMARY_FLOW = 'summary.flow';
    private const SUMMARY_MAIL = 'summary.mail';
    private const SUMMARY_WEBHOOK = 'summary.webhook';
    private const SUMMARY_MEDIA = 'summary.media';
    private const SUMMARY_PRODUCT_EXPORT = 'summary.product_export';
    private const SUMMARY_SITEMAP = 'summary.sitemap';
    private const SUMMARY_SCHEDULED_TASK = 'summary.scheduled_task';
    private const SUMMARY_PLUGIN = 'summary.plugin';
    private const SUMMARY_PAYPAL = 'summary.paypal';
    private const SUMMARY_FALLBACK_ORDERS = 'summary.fallback.orders';
    private const SUMMARY_FALLBACK_PAYMENTS = 'summary.fallback.payments';
    private const SUMMARY_FALLBACK_COMMUNICATION = 'summary.fallback.communication';
    private const SUMMARY_FALLBACK_INTEGRATIONS = 'summary.fallback.integrations';
    private const SUMMARY_FALLBACK_CONTENT = 'summary.fallback.content';
    private const SUMMARY_FALLBACK_SYSTEM = 'summary.fallback.system';

    /**
     * @param array<string, array<array-key, scalar|null>|list<string>|scalar|null> $row
     * @param list<string> $allowedActions
     *
     * @return array<string, array<array-key, scalar|null>|list<string>|scalar|null>
     */
    public function formatRow(array $row, array $allowedActions, string $availableActions, string $locale): array
    {
        $messageClass = (string) ($row['message_class'] ?? '');
        $source = (string) ($row['source'] ?? 'messenger');
        $subjectType = isset($row['subject_type']) ? (string) $row['subject_type'] : null;
        $status = (string) ($row['status'] ?? '');

        $messageTypeKey = $this->resolveMessageTypeKey($messageClass, $source, $subjectType);
        $topicGroupKey = $this->resolveTopicGroupKey($messageClass, $source, $subjectType);

        $row['message_name'] = $this->resolveMessageName($messageClass, $source, $subjectType, $status, $locale);
        $row['message_type'] = $this->translate($locale, $messageTypeKey);
        $row['topic_group'] = $this->translate($locale, $topicGroupKey);
        $row['allowed_actions'] = $allowedActions;
        $row['available_actions'] = $source === 'messenger' ? $availableActions : $this->translate($locale, self::LABEL_DETAILS);
        $row['status_label'] = $this->resolveStatusLabel($status, $source, $subjectType, $locale);
        $row['business_summary'] = $this->resolveBusinessSummary($messageTypeKey, $topicGroupKey, $source, $locale);
        $row['business_impact'] = $this->resolveBusinessImpact($status, $messageTypeKey, $topicGroupKey, $source, $locale);
        $row['source_label'] = $source === 'state_change'
            ? $this->translate($locale, self::LABEL_STATE_CHANGE)
            : $this->translate($locale, self::LABEL_MESSENGER);

        return $row;
    }

    /**
     * @param array<string, array<array-key, scalar|null>|list<string>|scalar|null> $row
     *
     * @return array<string, array<array-key, scalar|null>|list<string>|scalar|null>
     */
    public function formatGroupedStateChangeRow(array $row, string $locale): array
    {
        $businessReference = (string) ($row['business_reference'] ?? '');
        $orderLabel = $this->translate($locale, self::TYPE_ORDER);

        $row['message_name'] = $businessReference !== '' ? $orderLabel . ' ' . $businessReference : $orderLabel;
        $row['message_type'] = $orderLabel;
        $row['topic_group'] = $this->translate($locale, self::GROUP_ORDERS);
        $row['available_actions'] = $this->translate($locale, self::LABEL_DETAILS);
        $row['allowed_actions'] = [];

        return $row;
    }

    /**
     * @param array<string, array<array-key, scalar|null>|list<string>|scalar|null> $groupedRow
     * @param array<string, array<array-key, scalar|null>|list<string>|scalar|null> $candidate
     */
    public function shouldReplaceGroupedStateChange(array $groupedRow, array $candidate): bool
    {
        $currentSubjectPriority = $this->stateChangeSubjectPriority((string) ($groupedRow['subject_type'] ?? ''));
        $candidateSubjectPriority = $this->stateChangeSubjectPriority((string) ($candidate['subject_type'] ?? ''));
        $currentCreatedAt = (string) ($groupedRow['created_at'] ?? '');
        $candidateCreatedAt = (string) ($candidate['created_at'] ?? '');
        $hasHigherStatusPriority = $this->stateChangeStatusPriority((string) ($candidate['status'] ?? ''))
            > $this->stateChangeStatusPriority((string) ($groupedRow['status'] ?? ''));
        $hasNewerOrHigherPriorityEvent = $candidateCreatedAt > $currentCreatedAt
            || ($candidateCreatedAt === $currentCreatedAt && $hasHigherStatusPriority);

        return $candidateSubjectPriority > $currentSubjectPriority
            || ($candidateSubjectPriority === $currentSubjectPriority && $hasNewerOrHigherPriorityEvent);
    }

    private function resolveMessageName(string $messageClass, string $source, ?string $subjectType, string $status, string $locale): string
    {
        if ($source === 'state_change') {
            $statusLabelKey = match ($subjectType) {
                'order' => self::TYPE_ORDER_STATUS,
                'order_transaction' => self::TYPE_PAYMENT_STATUS,
                'order_delivery' => self::TYPE_DELIVERY_STATUS,
                default => self::TYPE_STATE_CHANGE,
            };

            return $this->translate($locale, $statusLabelKey) . ': ' . $status;
        }

        $profile = $this->resolveMessageClassProfile($messageClass);

        if ($profile['name'] !== null) {
            return $profile['name'] === self::NAME_GENERATE_PRODUCT_EXPORT
                ? $this->translate($locale, self::NAME_GENERATE_PRODUCT_EXPORT)
                : $profile['name'];
        }

        $segments = explode('\\', $messageClass);

        return $this->humanizeClassName($segments[\count($segments) - 1]);
    }

    private function resolveMessageTypeKey(string $messageClass, string $source, ?string $subjectType): string
    {
        if ($source === 'state_change') {
            return match ($subjectType) {
                'order' => self::TYPE_ORDER_STATUS,
                'order_transaction' => self::TYPE_PAYMENT_STATUS,
                'order_delivery' => self::TYPE_DELIVERY_STATUS,
                default => self::TYPE_STATE_CHANGE,
            };
        }

        $profile = $this->resolveMessageClassProfile($messageClass);

        if ($profile['type'] !== null) {
            return $profile['type'];
        }

        return match (true) {
            str_contains($messageClass, '\\Checkout\\Order\\') => self::TYPE_ORDER,
            str_contains($messageClass, '\\Checkout\\Payment\\') => self::TYPE_PAYMENT,
            str_contains($messageClass, '\\DataAbstractionLayer\\Indexing\\') => self::TYPE_INDEXER,
            str_contains($messageClass, '\\Content\\Flow\\') => self::TYPE_FLOW,
            str_contains($messageClass, '\\Content\\Mail\\') => self::TYPE_MAIL,
            str_contains($messageClass, '\\Framework\\Webhook\\') => self::TYPE_WEBHOOK,
            str_contains($messageClass, '\\Content\\Media\\') => self::TYPE_MEDIA,
            str_contains($messageClass, '\\ScheduledTask\\') => self::TYPE_SCHEDULED_TASK,
            str_contains($messageClass, 'MessengerHistoryDashboard\\') => self::TYPE_PLUGIN,
            str_contains($messageClass, 'Swag\\PayPal\\') => self::TYPE_PAYPAL,
            default => self::TYPE_MISC,
        };
    }

    private function resolveTopicGroupKey(string $messageClass, string $source, ?string $subjectType): string
    {
        if ($source === 'state_change') {
            return match ($subjectType) {
                'order', 'order_delivery' => self::GROUP_ORDERS,
                'order_transaction' => self::GROUP_PAYMENTS,
                default => self::GROUP_SYSTEM,
            };
        }

        $profile = $this->resolveMessageClassProfile($messageClass);

        if ($profile['topic_group'] !== null) {
            return $profile['topic_group'];
        }

        return match (true) {
            str_contains($messageClass, '\\Checkout\\Order\\') => self::GROUP_ORDERS,
            str_contains($messageClass, '\\Checkout\\Payment\\') => self::GROUP_PAYMENTS,
            str_contains($messageClass, '\\Content\\Mail\\') => self::GROUP_COMMUNICATION,
            str_contains($messageClass, '\\Framework\\Webhook\\'),
            str_contains($messageClass, 'Swag\\PayPal\\'),
            str_contains($messageClass, '\\Content\\ProductExport\\') => self::GROUP_INTEGRATIONS,
            str_contains($messageClass, '\\Content\\Media\\'),
            str_contains($messageClass, '\\Content\\Category\\'),
            str_contains($messageClass, '\\Content\\Rule\\'),
            str_contains($messageClass, '\\Content\\Sitemap\\') => self::GROUP_CONTENT,
            default => self::GROUP_SYSTEM,
        };
    }

    private function resolveStatusLabel(string $status, string $source, ?string $subjectType, string $locale): string
    {
        if ($source === 'state_change' && \in_array($subjectType, ['order', 'order_transaction', 'order_delivery'], true)) {
            return match ($status) {
                'open' => $this->translate($locale, 'status.open'),
                'in_progress' => $this->translate($locale, 'status.in_progress'),
                'completed' => $this->translate($locale, 'status.completed'),
                'cancelled' => $this->translate($locale, 'status.cancelled'),
                'paid' => $this->translate($locale, 'status.paid'),
                'reminded' => $this->translate($locale, 'status.reminded'),
                'failed' => $this->translate($locale, 'status.failed'),
                'shipped' => $this->translate($locale, 'status.shipped'),
                'shipped_partially' => $this->translate($locale, 'status.shipped_partially'),
                default => $status,
            };
        }

        return match ($status) {
            'dispatched' => $this->translate($locale, 'status.dispatched'),
            'received' => $this->translate($locale, 'status.received'),
            'handled' => $this->translate($locale, 'status.handled'),
            'failed' => $this->translate($locale, 'status.failed'),
            default => $status,
        };
    }

    private function resolveBusinessSummary(string $messageTypeKey, string $topicGroupKey, string $source, string $locale): string
    {
        if ($source === 'state_change') {
            return $this->translate($locale, match ($messageTypeKey) {
                self::TYPE_ORDER_STATUS => self::SUMMARY_ORDER_STATUS,
                self::TYPE_PAYMENT_STATUS => self::SUMMARY_PAYMENT_STATUS,
                self::TYPE_DELIVERY_STATUS => self::SUMMARY_DELIVERY_STATUS,
                default => self::SUMMARY_STATE_CHANGE,
            });
        }

        $summaryKey = match ($messageTypeKey) {
            self::TYPE_ORDER => self::SUMMARY_ORDER,
            self::TYPE_PAYMENT => self::SUMMARY_PAYMENT,
            self::TYPE_INDEXER => self::SUMMARY_INDEXER,
            self::TYPE_FLOW => self::SUMMARY_FLOW,
            self::TYPE_MAIL => self::SUMMARY_MAIL,
            self::TYPE_WEBHOOK => self::SUMMARY_WEBHOOK,
            self::TYPE_MEDIA => self::SUMMARY_MEDIA,
            self::TYPE_PRODUCT_EXPORT => self::SUMMARY_PRODUCT_EXPORT,
            self::TYPE_SITEMAP => self::SUMMARY_SITEMAP,
            self::TYPE_SCHEDULED_TASK => self::SUMMARY_SCHEDULED_TASK,
            self::TYPE_PLUGIN => self::SUMMARY_PLUGIN,
            self::TYPE_PAYPAL => self::SUMMARY_PAYPAL,
            default => match ($topicGroupKey) {
                self::GROUP_ORDERS => self::SUMMARY_FALLBACK_ORDERS,
                self::GROUP_PAYMENTS => self::SUMMARY_FALLBACK_PAYMENTS,
                self::GROUP_COMMUNICATION => self::SUMMARY_FALLBACK_COMMUNICATION,
                self::GROUP_INTEGRATIONS => self::SUMMARY_FALLBACK_INTEGRATIONS,
                self::GROUP_CONTENT => self::SUMMARY_FALLBACK_CONTENT,
                default => self::SUMMARY_FALLBACK_SYSTEM,
            },
        };

        return $this->translate($locale, $summaryKey);
    }

    /**
     * @return array{name:?string, type:?string, topic_group:?string}
     */
    private function resolveMessageClassProfile(string $messageClass): array
    {
        $exactProfiles = [
            'Shopware\Core\Content\ProductExport\ScheduledTask\ProductExportGenerateTask' => [
                'name' => self::NAME_GENERATE_PRODUCT_EXPORT,
                'type' => self::TYPE_PRODUCT_EXPORT,
                'topic_group' => self::GROUP_INTEGRATIONS,
            ],
        ];

        if (isset($exactProfiles[$messageClass])) {
            return $exactProfiles[$messageClass];
        }

        $matchedPrefixProfile = null;

        foreach ($this->prefixProfiles() as $prefix => $profile) {
            if (str_starts_with($messageClass, $prefix)) {
                $matchedPrefixProfile = $profile;

                break;
            }
        }

        if ($matchedPrefixProfile !== null) {
            return $matchedPrefixProfile;
        }

        return str_contains($messageClass, '\\ScheduledTask\\')
            ? [
                'name' => null,
                'type' => self::TYPE_SCHEDULED_TASK,
                'topic_group' => self::GROUP_SYSTEM,
            ]
            : ['name' => null, 'type' => null, 'topic_group' => null];
    }

    /**
     * @return array<string, array{name:?string, type:?string, topic_group:?string}>
     */
    private function prefixProfiles(): array
    {
        return [
            'Shopware\Core\Content\ProductExport\\' => ['name' => null, 'type' => self::TYPE_PRODUCT_EXPORT, 'topic_group' => self::GROUP_INTEGRATIONS],
            'Shopware\Core\Content\Sitemap\\' => ['name' => null, 'type' => self::TYPE_SITEMAP, 'topic_group' => self::GROUP_CONTENT],
            'Shopware\Core\Content\Media\\' => ['name' => null, 'type' => self::TYPE_MEDIA, 'topic_group' => self::GROUP_CONTENT],
            'Shopware\Core\Content\Mail\\' => ['name' => null, 'type' => self::TYPE_MAIL, 'topic_group' => self::GROUP_COMMUNICATION],
            'Shopware\Core\Content\Flow\\' => ['name' => null, 'type' => self::TYPE_FLOW, 'topic_group' => self::GROUP_ORDERS],
            'Shopware\Core\Framework\Webhook\\' => ['name' => null, 'type' => self::TYPE_WEBHOOK, 'topic_group' => self::GROUP_INTEGRATIONS],
            'Shopware\Core\Framework\DataAbstractionLayer\Indexing\\' => ['name' => null, 'type' => self::TYPE_INDEXER, 'topic_group' => self::GROUP_SYSTEM],
            'Shopware\Core\Checkout\Order\\' => ['name' => null, 'type' => self::TYPE_ORDER, 'topic_group' => self::GROUP_ORDERS],
            'Shopware\Core\Checkout\Payment\\' => ['name' => null, 'type' => self::TYPE_PAYMENT, 'topic_group' => self::GROUP_PAYMENTS],
            'Swag\PayPal\\' => ['name' => null, 'type' => self::TYPE_PAYPAL, 'topic_group' => self::GROUP_INTEGRATIONS],
            'MessengerHistoryDashboard\\' => ['name' => null, 'type' => self::TYPE_PLUGIN, 'topic_group' => self::GROUP_SYSTEM],
        ];
    }

    private function humanizeClassName(string $className): string
    {
        $trimmedClassName = preg_replace('/(Message|Task|Event|Handler)$/', '', $className) ?? $className;
        $humanized = preg_replace('/(?<!^)([A-Z])/', ' $1', $trimmedClassName) ?? $trimmedClassName;

        return trim($humanized);
    }

    private function resolveBusinessImpact(string $status, string $messageTypeKey, string $topicGroupKey, string $source, string $locale): string
    {
        if ($source === 'state_change') {
            return match ($status) {
                'failed', 'cancelled' => $this->translate($locale, self::LABEL_REVIEW),
                'reminded', 'in_progress', 'shipped_partially' => $this->translate($locale, self::LABEL_MONITOR),
                default => $this->translate($locale, self::LABEL_LOW),
            };
        }

        if ($status !== 'failed') {
            return $this->translate($locale, self::LABEL_LOW);
        }

        return match ($messageTypeKey) {
            self::TYPE_ORDER,
            self::TYPE_PAYMENT,
            self::TYPE_WEBHOOK,
            self::TYPE_PAYPAL,
            self::TYPE_MAIL => $this->translate($locale, self::LABEL_REVIEW),
            self::TYPE_INDEXER,
            self::TYPE_FLOW => $this->translate($locale, self::LABEL_MONITOR),
            default => match ($topicGroupKey) {
                self::GROUP_ORDERS,
                self::GROUP_PAYMENTS,
                self::GROUP_COMMUNICATION,
                self::GROUP_INTEGRATIONS => $this->translate($locale, self::LABEL_REVIEW),
                default => $this->translate($locale, self::LABEL_MONITOR),
            },
        };
    }

    private function translate(string $locale, string $key): string
    {
        $language = str_starts_with(strtolower($locale), 'en') ? 'en' : 'de';

        return self::translations()[$language][$key] ?? $key;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function translations(): array
    {
        return [
            'de' => [
                self::TYPE_ORDER_STATUS => 'Bestellstatus',
                self::TYPE_PAYMENT_STATUS => 'Zahlungsstatus',
                self::TYPE_DELIVERY_STATUS => 'Lieferstatus',
                self::TYPE_STATE_CHANGE => 'Statuswechsel',
                self::TYPE_ORDER => 'Bestellung',
                self::TYPE_PAYMENT => 'Zahlung',
                self::TYPE_INDEXER => 'Indexer',
                self::TYPE_FLOW => 'Ablauf',
                self::TYPE_MAIL => 'E-Mail',
                self::TYPE_WEBHOOK => 'Webhook',
                self::TYPE_MEDIA => 'Medien',
                self::TYPE_PRODUCT_EXPORT => 'Produkt-Export',
                self::TYPE_SITEMAP => 'Sitemap',
                self::TYPE_SCHEDULED_TASK => 'Geplanter Task',
                self::TYPE_PLUGIN => 'Plugin',
                self::TYPE_PAYPAL => 'PayPal',
                self::TYPE_MISC => 'Sonstiges',
                self::GROUP_ORDERS => 'Bestellungen',
                self::GROUP_PAYMENTS => 'Zahlungen',
                self::GROUP_COMMUNICATION => 'Kommunikation',
                self::GROUP_INTEGRATIONS => 'Integrationen',
                self::GROUP_CONTENT => 'Inhalte',
                self::GROUP_SYSTEM => 'System',
                self::LABEL_DETAILS => 'Details',
                self::LABEL_MESSENGER => 'Messenger',
                self::LABEL_STATE_CHANGE => 'Statuswechsel',
                self::LABEL_REVIEW => 'Prüfen',
                self::LABEL_MONITOR => 'Beobachten',
                self::LABEL_LOW => 'Unkritisch',
                self::NAME_GENERATE_PRODUCT_EXPORT => 'Produkt-Export erzeugen',
                self::SUMMARY_ORDER_STATUS => 'Dokumentiert synchrone Statuswechsel einer Bestellung.',
                self::SUMMARY_PAYMENT_STATUS => 'Dokumentiert synchrone Statuswechsel einer Zahlungstransaktion.',
                self::SUMMARY_DELIVERY_STATUS => 'Dokumentiert synchrone Statuswechsel einer Lieferung.',
                self::SUMMARY_STATE_CHANGE => 'Dokumentiert synchrone Statuswechsel im Shop.',
                self::SUMMARY_ORDER => 'Bearbeitet Hintergrundaufgaben rund um Bestellungen.',
                self::SUMMARY_PAYMENT => 'Bearbeitet Hintergrundaufgaben rund um Zahlungen und Zahlungsarten.',
                self::SUMMARY_INDEXER => 'Aktualisiert interne Shop-Daten und Suchindizes.',
                self::SUMMARY_FLOW => 'Führt automatisierte Abläufe und Regeln aus.',
                self::SUMMARY_MAIL => 'Bearbeitet E-Mail-Versand oder E-Mail-Folgen.',
                self::SUMMARY_WEBHOOK => 'Überträgt Daten an externe Systeme.',
                self::SUMMARY_MEDIA => 'Verarbeitet Bilder und Mediendateien.',
                self::SUMMARY_PRODUCT_EXPORT => 'Erzeugt Exportdateien für Produktfeeds und externe Kanäle.',
                self::SUMMARY_SITEMAP => 'Erzeugt oder aktualisiert Sitemaps für den Shop.',
                self::SUMMARY_SCHEDULED_TASK => 'Führt eine geplante Hintergrundaufgabe im Shop aus.',
                self::SUMMARY_PLUGIN => 'Verarbeitet plugin-spezifische Hintergrundaufgaben.',
                self::SUMMARY_PAYPAL => 'Verarbeitet PayPal-bezogene Hintergrundaufgaben.',
                self::SUMMARY_FALLBACK_ORDERS => 'Bearbeitet Hintergrundaufgaben mit Bezug zu Bestellungen.',
                self::SUMMARY_FALLBACK_PAYMENTS => 'Bearbeitet Hintergrundaufgaben mit Bezug zu Zahlungen.',
                self::SUMMARY_FALLBACK_COMMUNICATION => 'Bearbeitet Hintergrundaufgaben mit Bezug zu E-Mails oder Benachrichtigungen.',
                self::SUMMARY_FALLBACK_INTEGRATIONS => 'Bearbeitet Hintergrundaufgaben mit Bezug zu externen Systemen.',
                self::SUMMARY_FALLBACK_CONTENT => 'Bearbeitet Hintergrundaufgaben mit Bezug zu Inhalten und Katalogdaten.',
                self::SUMMARY_FALLBACK_SYSTEM => 'Bearbeitet allgemeine Hintergrundaufgaben im Shop.',
                'status.open' => 'Offen',
                'status.in_progress' => 'In Bearbeitung',
                'status.completed' => 'Abgeschlossen',
                'status.cancelled' => 'Storniert',
                'status.paid' => 'Bezahlt',
                'status.reminded' => 'Erinnert',
                'status.failed' => 'Fehlgeschlagen',
                'status.shipped' => 'Versandt',
                'status.shipped_partially' => 'Teilversandt',
                'status.dispatched' => 'Gesendet',
                'status.received' => 'Empfangen',
                'status.handled' => 'Verarbeitet',
            ],
            'en' => [
                self::TYPE_ORDER_STATUS => 'Order status',
                self::TYPE_PAYMENT_STATUS => 'Payment status',
                self::TYPE_DELIVERY_STATUS => 'Delivery status',
                self::TYPE_STATE_CHANGE => 'State change',
                self::TYPE_ORDER => 'Order',
                self::TYPE_PAYMENT => 'Payment',
                self::TYPE_INDEXER => 'Indexer',
                self::TYPE_FLOW => 'Flow',
                self::TYPE_MAIL => 'Mail',
                self::TYPE_WEBHOOK => 'Webhook',
                self::TYPE_MEDIA => 'Media',
                self::TYPE_PRODUCT_EXPORT => 'Product export',
                self::TYPE_SITEMAP => 'Sitemap',
                self::TYPE_SCHEDULED_TASK => 'Scheduled task',
                self::TYPE_PLUGIN => 'Plugin',
                self::TYPE_PAYPAL => 'PayPal',
                self::TYPE_MISC => 'Miscellaneous',
                self::GROUP_ORDERS => 'Orders',
                self::GROUP_PAYMENTS => 'Payments',
                self::GROUP_COMMUNICATION => 'Communication',
                self::GROUP_INTEGRATIONS => 'Integrations',
                self::GROUP_CONTENT => 'Content',
                self::GROUP_SYSTEM => 'System',
                self::LABEL_DETAILS => 'Details',
                self::LABEL_MESSENGER => 'Messenger',
                self::LABEL_STATE_CHANGE => 'State change',
                self::LABEL_REVIEW => 'Review',
                self::LABEL_MONITOR => 'Monitor',
                self::LABEL_LOW => 'Low',
                self::NAME_GENERATE_PRODUCT_EXPORT => 'Generate product export',
                self::SUMMARY_ORDER_STATUS => 'Documents synchronous status changes of an order.',
                self::SUMMARY_PAYMENT_STATUS => 'Documents synchronous status changes of a payment transaction.',
                self::SUMMARY_DELIVERY_STATUS => 'Documents synchronous status changes of a delivery.',
                self::SUMMARY_STATE_CHANGE => 'Documents synchronous status changes in the shop.',
                self::SUMMARY_ORDER => 'Processes background tasks related to orders.',
                self::SUMMARY_PAYMENT => 'Processes background tasks related to payments and payment methods.',
                self::SUMMARY_INDEXER => 'Updates internal shop data and search indexes.',
                self::SUMMARY_FLOW => 'Executes automated flows and rules.',
                self::SUMMARY_MAIL => 'Processes mail delivery or follow-up mails.',
                self::SUMMARY_WEBHOOK => 'Transfers data to external systems.',
                self::SUMMARY_MEDIA => 'Processes images and media files.',
                self::SUMMARY_PRODUCT_EXPORT => 'Generates export files for product feeds and external channels.',
                self::SUMMARY_SITEMAP => 'Generates or updates sitemaps for the shop.',
                self::SUMMARY_SCHEDULED_TASK => 'Executes a scheduled background task in the shop.',
                self::SUMMARY_PLUGIN => 'Processes plugin-specific background tasks.',
                self::SUMMARY_PAYPAL => 'Processes PayPal-related background tasks.',
                self::SUMMARY_FALLBACK_ORDERS => 'Processes background tasks related to orders.',
                self::SUMMARY_FALLBACK_PAYMENTS => 'Processes background tasks related to payments.',
                self::SUMMARY_FALLBACK_COMMUNICATION => 'Processes background tasks related to mails or notifications.',
                self::SUMMARY_FALLBACK_INTEGRATIONS => 'Processes background tasks related to external systems.',
                self::SUMMARY_FALLBACK_CONTENT => 'Processes background tasks related to content and catalog data.',
                self::SUMMARY_FALLBACK_SYSTEM => 'Processes general background tasks in the shop.',
                'status.open' => 'Open',
                'status.in_progress' => 'In progress',
                'status.completed' => 'Completed',
                'status.cancelled' => 'Cancelled',
                'status.paid' => 'Paid',
                'status.reminded' => 'Reminded',
                'status.failed' => 'Failed',
                'status.shipped' => 'Shipped',
                'status.shipped_partially' => 'Partially shipped',
                'status.dispatched' => 'Dispatched',
                'status.received' => 'Received',
                'status.handled' => 'Handled',
            ],
        ];
    }

    private function stateChangeSubjectPriority(string $subjectType): int
    {
        return match ($subjectType) {
            'order' => 3,
            'order_delivery' => 2,
            'order_transaction' => 1,
            default => 0,
        };
    }

    private function stateChangeStatusPriority(string $status): int
    {
        return match ($status) {
            'completed', 'paid', 'shipped' => 3,
            'in_progress', 'reminded', 'shipped_partially' => 2,
            'open' => 1,
            default => 0,
        };
    }
}
