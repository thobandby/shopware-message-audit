<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard;

use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class MessengerHistoryDashboard extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $this->buildDefaultConfig($container);
    }
}
