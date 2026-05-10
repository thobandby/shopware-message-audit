<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Operator;

final class OperatorActionPolicy
{
    private const ALLOWED_ACTIONS = [
        'failed' => ['retry', 'quarantine', 'dismiss'],
        'received' => ['quarantine', 'dismiss'],
        'dispatched' => ['quarantine', 'dismiss'],
        'handled' => ['dismiss'],
    ];

    /**
     * @return list<string>
     */
    public function allowedActions(string $status): array
    {
        return self::ALLOWED_ACTIONS[$status] ?? [];
    }

    public function isAllowed(string $action, string $status): bool
    {
        return \in_array($action, $this->allowedActions($status), true);
    }

    public function formatAllowedActions(string $status): string
    {
        $labels = array_map([$this, 'resolveActionLabel'], $this->allowedActions($status));

        return $labels === [] ? 'Details' : implode(', ', $labels);
    }

    public function resolveActionLabel(string $action): string
    {
        return match ($action) {
            'retry' => 'Erneut senden',
            'quarantine' => 'Ausblenden',
            'dismiss' => 'Erledigen',
            default => $action,
        };
    }
}
