<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Repository;

final class MessageRowGrouper
{
    public function __construct(
        private readonly MessagePresentationFormatter $presentationFormatter
    ) {
    }

    /**
     * @param list<array<string, array<array-key, scalar|null>|list<string>|scalar|null>> $rows
     *
     * @return list<array<string, array<array-key, scalar|null>|list<string>|scalar|null>>
     */
    public function groupRows(array $rows, string $locale): array
    {
        $groupedRows = [];
        $messengerGroups = [];

        foreach ($rows as $row) {
            $source = (string) ($row['source'] ?? '');

            if ($source === 'state_change') {
                $this->groupStateChangeRow($groupedRows, $row, $locale);

                continue;
            }

            if (! $this->shouldGroupMessengerRow($row)) {
                $groupedRows[] = $row;

                continue;
            }

            $this->groupMessengerRow($groupedRows, $messengerGroups, $this->buildMessengerGroupKey($row), $row);
        }

        $this->finalizeMessengerGroups($groupedRows, $messengerGroups, $locale);

        return array_values($groupedRows);
    }

    /**
     * @param array<int|string, array<string, array<array-key, scalar|null>|list<string>|scalar|null>> $groupedRows
     * @param array<string, array<array-key, scalar|null>|list<string>|scalar|null> $row
     */
    private function groupStateChangeRow(array &$groupedRows, array $row, string $locale): void
    {
        $groupKey = $this->buildStateChangeGroupKey($row);

        if (! isset($groupedRows[$groupKey])) {
            $row['group_count'] = 1;
            $groupedRows[$groupKey] = $this->presentationFormatter->formatGroupedStateChangeRow($row, $locale);

            return;
        }

        $groupedRows[$groupKey]['group_count'] = (int) ($groupedRows[$groupKey]['group_count'] ?? 1) + 1;

        if (! $this->presentationFormatter->shouldReplaceGroupedStateChange($groupedRows[$groupKey], $row)) {
            return;
        }

        $row['group_count'] = (int) $groupedRows[$groupKey]['group_count'];
        $groupedRows[$groupKey] = $this->presentationFormatter->formatGroupedStateChangeRow($row, $locale);
    }

    /**
     * @param array<int|string, array<string, array<array-key, scalar|null>|list<string>|scalar|null>> $groupedRows
     * @param array<string, array{count:int, row:array<string, array<array-key, scalar|null>|list<string>|scalar|null>}> $messengerGroups
     * @param array<string, array<array-key, scalar|null>|list<string>|scalar|null> $row
     */
    private function groupMessengerRow(array &$groupedRows, array &$messengerGroups, string $groupKey, array $row): void
    {
        if (! isset($messengerGroups[$groupKey])) {
            $messengerGroups[$groupKey] = [
                'count' => 1,
                'row' => $row,
            ];
            $groupedRows[$groupKey] = $row;

            return;
        }

        $messengerGroups[$groupKey]['count']++;

        if (! $this->presentationFormatter->shouldReplaceGroupedMessengerRow($messengerGroups[$groupKey]['row'], $row)) {
            return;
        }

        $messengerGroups[$groupKey]['row'] = $row;
        $groupedRows[$groupKey] = $row;
    }

    /**
     * @param array<int|string, array<string, array<array-key, scalar|null>|list<string>|scalar|null>> $groupedRows
     * @param array<string, array{count:int, row:array<string, array<array-key, scalar|null>|list<string>|scalar|null>}> $messengerGroups
     */
    private function finalizeMessengerGroups(array &$groupedRows, array $messengerGroups, string $locale): void
    {
        foreach ($messengerGroups as $groupKey => $group) {
            if ($group['count'] < 2) {
                continue;
            }

            $groupedRows[$groupKey] = $this->presentationFormatter->formatGroupedMessengerRow(
                $group['row'],
                $locale,
                $group['count']
            );
        }
    }

    /**
     * @param array<string, array<array-key, scalar|null>|list<string>|scalar|null> $row
     */
    private function buildStateChangeGroupKey(array $row): string
    {
        $businessReference = (string) ($row['business_reference'] ?? '');

        if ($businessReference !== '') {
            return 'state_change:' . strtolower($businessReference);
        }

        return 'state_change:' . (string) ($row['subject_type'] ?? '') . ':' . (string) ($row['subject_id'] ?? '');
    }

    /**
     * @param array<string, array<array-key, scalar|null>|list<string>|scalar|null> $row
     */
    private function shouldGroupMessengerRow(array $row): bool
    {
        if (($row['source'] ?? null) !== 'messenger') {
            return false;
        }

        $subjectType = (string) ($row['subject_type'] ?? '');
        $businessReference = (string) ($row['business_reference'] ?? '');

        return $subjectType === '' && $businessReference === '';
    }

    /**
     * @param array<string, array<array-key, scalar|null>|list<string>|scalar|null> $row
     */
    private function buildMessengerGroupKey(array $row): string
    {
        $messageClass = (string) ($row['message_class'] ?? '');
        $createdAt = \DateTimeImmutable::createFromFormat(
            MessageWhereClauseBuilder::DATETIME_FORMAT,
            (string) ($row['created_at'] ?? '')
        );

        if (! $createdAt instanceof \DateTimeImmutable) {
            $createdAt = new \DateTimeImmutable('@0');
        }

        return \sprintf(
            'messenger:%s:%s',
            strtolower($messageClass),
            $createdAt->format('Y-m-d H:i')
        );
    }
}
