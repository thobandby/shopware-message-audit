<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Monitoring;

use Thorsten\MessengerHistory\Messenger\Contract\MetricsExporterInterface;

final readonly class NullMetricsExporter implements MetricsExporterInterface
{
    public function exportPrometheus(): string
    {
        return '';
    }
}
