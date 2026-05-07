<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Monitoring;

use Thorsten\MessengerHistory\Messenger\Contract\OperatorActionExecutorInterface;
use Thorsten\MessengerHistory\Messenger\Model\OperatorActionRequest;
use Thorsten\MessengerHistory\Messenger\Model\OperatorActionResult;

final class NoopOperatorActionExecutor implements OperatorActionExecutorInterface
{
    public function retryNow(OperatorActionRequest $request): OperatorActionResult
    {
        return new OperatorActionResult(bin2hex(random_bytes(16)), 'accepted', $request->messageUuid, 'retry_requested');
    }

    public function retryDelayed(OperatorActionRequest $request, int $delayMs): OperatorActionResult
    {
        return new OperatorActionResult(bin2hex(random_bytes(16)), 'accepted', $request->messageUuid, 'retry_requested', ['delayMs' => $delayMs]);
    }

    public function quarantine(OperatorActionRequest $request): OperatorActionResult
    {
        return new OperatorActionResult(bin2hex(random_bytes(16)), 'accepted', $request->messageUuid, 'quarantined');
    }

    public function dismiss(OperatorActionRequest $request): OperatorActionResult
    {
        return new OperatorActionResult(bin2hex(random_bytes(16)), 'accepted', $request->messageUuid, 'dismissed');
    }

    public function requeueToTransport(OperatorActionRequest $request, string $targetTransport): OperatorActionResult
    {
        return new OperatorActionResult(bin2hex(random_bytes(16)), 'accepted', $request->messageUuid, 'retried', ['targetTransport' => $targetTransport]);
    }
}
