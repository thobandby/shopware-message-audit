<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Contract;

interface MetricsExporterInterface
{
    public function exportPrometheus(): string;
}
