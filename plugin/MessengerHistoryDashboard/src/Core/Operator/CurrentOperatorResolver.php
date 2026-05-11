<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Operator;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;

final class CurrentOperatorResolver
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function resolve(Context $context): OperatorIdentity
    {
        $source = $context->getSource();

        if (! $source instanceof AdminApiSource) {
            return new OperatorIdentity(null, 'system', 'system');
        }

        $identity = $this->resolveUserIdentity($source->getUserId());
        if ($identity === null) {
            $integrationId = $source->getIntegrationId();
            if (\is_string($integrationId) && $integrationId !== '') {
                $identity = new OperatorIdentity($integrationId, 'integration', 'integration:' . $integrationId);
            }
        }

        return $identity ?? new OperatorIdentity(null, 'admin-api', 'admin-api');
    }

    private function resolveUserIdentity(string|null $userId): ?OperatorIdentity
    {
        if (! \is_string($userId) || $userId === '') {
            return null;
        }

        $user = $this->connection->fetchAssociative(
            'SELECT first_name, last_name, email, username FROM user WHERE id = :id',
            ['id' => hex2bin(str_replace('-', '', $userId))]
        );

        if (! \is_array($user)) {
            return new OperatorIdentity($userId, 'admin-user', 'admin-user:' . $userId);
        }

        $email = $user['email'] ?? null;

        return new OperatorIdentity(
            $userId,
            'admin-user',
            $this->resolveUserLabel($user, $userId),
            \is_string($email) && $email !== '' ? $email : null
        );
    }

    /**
     * @param array<string, string|null> $user
     */
    private function resolveUserLabel(array $user, string $fallback): string
    {
        $labelParts = array_filter([
            $user['first_name'] ?? null,
            $user['last_name'] ?? null,
        ], static fn (?string $value): bool => $value !== null && trim($value) !== '');
        $label = trim(implode(' ', $labelParts));

        if ($label !== '') {
            return $label;
        }

        return (string) ($user['email'] ?? $user['username'] ?? $fallback);
    }
}
