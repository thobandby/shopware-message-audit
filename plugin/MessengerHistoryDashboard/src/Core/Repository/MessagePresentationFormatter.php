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
    private const NAME_MESSENGER_RETENTION_CLEANUP = 'name.messenger_retention_cleanup';
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
    private const SUMMARY_MESSENGER_RETENTION_CLEANUP = 'summary.messenger_retention_cleanup';
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
        $row['business_summary'] = $this->resolveBusinessSummary($messageClass, $messageTypeKey, $topicGroupKey, $source, $locale);
        $row['business_impact'] = $this->resolveBusinessImpact($status, $messageTypeKey, $topicGroupKey, $source, $locale);
        $row['source_label'] = $source === 'state_change'
            ? $this->translate($locale, self::LABEL_STATE_CHANGE)
            : $this->translate($locale, self::LABEL_MESSENGER);
        $row['attempt_count'] = $this->resolveAttemptCount($source, $status, (int) ($row['retry_count'] ?? 0));

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
     * @param array<string, array<array-key, scalar|null>|list<string>|scalar|null> $row
     *
     * @return array<string, array<array-key, scalar|null>|list<string>|scalar|null>
     */
    public function formatGroupedMessengerRow(array $row, string $locale, int $groupCount): array
    {
        $row['group_count'] = $groupCount;
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

    /**
     * @param array<string, array<array-key, scalar|null>|list<string>|scalar|null> $groupedRow
     * @param array<string, array<array-key, scalar|null>|list<string>|scalar|null> $candidate
     */
    public function shouldReplaceGroupedMessengerRow(array $groupedRow, array $candidate): bool
    {
        $currentCreatedAt = (string) ($groupedRow['created_at'] ?? '');
        $candidateCreatedAt = (string) ($candidate['created_at'] ?? '');

        if ($candidateCreatedAt !== $currentCreatedAt) {
            return $candidateCreatedAt > $currentCreatedAt;
        }

        return $this->messengerStatusPriority((string) ($candidate['status'] ?? ''))
            > $this->messengerStatusPriority((string) ($groupedRow['status'] ?? ''));
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
            return \in_array($profile['name'], [self::NAME_GENERATE_PRODUCT_EXPORT, self::NAME_MESSENGER_RETENTION_CLEANUP], true)
                ? $this->translate($locale, $profile['name'])
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

    private function resolveBusinessSummary(string $messageClass, string $messageTypeKey, string $topicGroupKey, string $source, string $locale): string
    {
        if ($source === 'state_change') {
            return $this->translate($locale, match ($messageTypeKey) {
                self::TYPE_ORDER_STATUS => self::SUMMARY_ORDER_STATUS,
                self::TYPE_PAYMENT_STATUS => self::SUMMARY_PAYMENT_STATUS,
                self::TYPE_DELIVERY_STATUS => self::SUMMARY_DELIVERY_STATUS,
                default => self::SUMMARY_STATE_CHANGE,
            });
        }

        $profile = $this->resolveMessageClassProfile($messageClass);

        if ($profile['summary'] !== null) {
            return $this->translate($locale, $profile['summary']);
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

    private function resolveAttemptCount(string $source, string $status, int $retryCount): int
    {
        if ($source !== 'messenger') {
            return 0;
        }

        if ($status === 'dispatched') {
            return 0;
        }

        return max(1, $retryCount + 1);
    }

    /**
     * @return array{name:?string, type:?string, topic_group:?string, summary:?string}
     */
    private function resolveMessageClassProfile(string $messageClass): array
    {
        $exactProfiles = [
            'Shopware\Core\Content\ProductExport\ScheduledTask\ProductExportGenerateTask' => [
                'name' => self::NAME_GENERATE_PRODUCT_EXPORT,
                'type' => self::TYPE_PRODUCT_EXPORT,
                'topic_group' => self::GROUP_INTEGRATIONS,
                'summary' => null,
            ],
            'MessengerHistoryDashboard\Core\ScheduledTask\CleanupShopwareMessengerRetentionTask' => [
                'name' => self::NAME_MESSENGER_RETENTION_CLEANUP,
                'type' => self::TYPE_SCHEDULED_TASK,
                'topic_group' => self::GROUP_SYSTEM,
                'summary' => self::SUMMARY_MESSENGER_RETENTION_CLEANUP,
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
                'summary' => null,
            ]
            : ['name' => null, 'type' => null, 'topic_group' => null, 'summary' => null];
    }

    /**
     * @return array<string, array{name:?string, type:?string, topic_group:?string, summary:?string}>
     */
    private function prefixProfiles(): array
    {
        return [
            'Shopware\Core\Content\ProductExport\\' => ['name' => null, 'type' => self::TYPE_PRODUCT_EXPORT, 'topic_group' => self::GROUP_INTEGRATIONS, 'summary' => null],
            'Shopware\Core\Content\Sitemap\\' => ['name' => null, 'type' => self::TYPE_SITEMAP, 'topic_group' => self::GROUP_CONTENT, 'summary' => null],
            'Shopware\Core\Content\Media\\' => ['name' => null, 'type' => self::TYPE_MEDIA, 'topic_group' => self::GROUP_CONTENT, 'summary' => null],
            'Shopware\Core\Content\Mail\\' => ['name' => null, 'type' => self::TYPE_MAIL, 'topic_group' => self::GROUP_COMMUNICATION, 'summary' => null],
            'Shopware\Core\Content\Flow\\' => ['name' => null, 'type' => self::TYPE_FLOW, 'topic_group' => self::GROUP_ORDERS, 'summary' => null],
            'Shopware\Core\Framework\Webhook\\' => ['name' => null, 'type' => self::TYPE_WEBHOOK, 'topic_group' => self::GROUP_INTEGRATIONS, 'summary' => null],
            'Shopware\Core\Framework\DataAbstractionLayer\Indexing\\' => ['name' => null, 'type' => self::TYPE_INDEXER, 'topic_group' => self::GROUP_SYSTEM, 'summary' => null],
            'Shopware\Core\Checkout\Order\\' => ['name' => null, 'type' => self::TYPE_ORDER, 'topic_group' => self::GROUP_ORDERS, 'summary' => null],
            'Shopware\Core\Checkout\Payment\\' => ['name' => null, 'type' => self::TYPE_PAYMENT, 'topic_group' => self::GROUP_PAYMENTS, 'summary' => null],
            'Swag\PayPal\\' => ['name' => null, 'type' => self::TYPE_PAYPAL, 'topic_group' => self::GROUP_INTEGRATIONS, 'summary' => null],
            'MessengerHistoryDashboard\\' => ['name' => null, 'type' => self::TYPE_PLUGIN, 'topic_group' => self::GROUP_SYSTEM, 'summary' => null],
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

    private function messengerStatusPriority(string $status): int
    {
        return match ($status) {
            'failed' => 4,
            'handled' => 3,
            'received' => 2,
            'dispatched' => 1,
            default => 0,
        };
    }

    private function translate(string $locale, string $key): string
    {
        return MessagePresentationTranslations::translate($locale, $key);
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
