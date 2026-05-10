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

        $userId = $source->getUserId();
        if (\is_string($userId) && $userId !== '') {
            $user = $this->connection->fetchAssociative(
                'SELECT first_name, last_name, email, username FROM user WHERE id = :id',
                ['id' => hex2bin(str_replace('-', '', $userId))]
            );

            if (\is_array($user)) {
                $labelParts = array_filter([
                    $user['first_name'] ?? null,
                    $user['last_name'] ?? null,
                ], static fn (?string $value): bool => $value !== null && trim($value) !== '');
                $label = trim(implode(' ', $labelParts));

                if ($label === '') {
                    $label = (string) ($user['email'] ?? $user['username'] ?? $userId);
                }

                $email = $user['email'] ?? null;

                return new OperatorIdentity(
                    $userId,
                    'admin-user',
                    $label,
                    \is_string($email) && $email !== '' ? $email : null
                );
            }

            return new OperatorIdentity($userId, 'admin-user', 'admin-user:' . $userId);
        }

        $integrationId = $source->getIntegrationId();
        if (\is_string($integrationId) && $integrationId !== '') {
            return new OperatorIdentity($integrationId, 'integration', 'integration:' . $integrationId);
        }

        return new OperatorIdentity(null, 'admin-api', 'admin-api');
    }
}
