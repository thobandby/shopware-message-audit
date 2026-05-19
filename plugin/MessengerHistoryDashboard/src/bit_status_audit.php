<?php

declare(strict_types=1);

namespace bit_status_audit;

use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class bit_status_audit extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $this->buildDefaultConfig($container);
    }

    public function getMigrationNamespace(): string
    {
        return 'MessengerHistoryDashboard\\Migration';
    }

    public function getMigrationPath(): string
    {
        return __DIR__ . '/Migration';
    }
}
