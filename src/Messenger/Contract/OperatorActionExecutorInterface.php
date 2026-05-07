<?php

declare(strict_types=1);

namespace Thorsten\MessengerHistory\Messenger\Contract;

use Thorsten\MessengerHistory\Messenger\Model\OperatorActionRequest;
use Thorsten\MessengerHistory\Messenger\Model\OperatorActionResult;

interface OperatorActionExecutorInterface
{
    public function retryNow(OperatorActionRequest $request): OperatorActionResult;

    public function retryDelayed(OperatorActionRequest $request, int $delayMs): OperatorActionResult;

    public function quarantine(OperatorActionRequest $request): OperatorActionResult;

    public function dismiss(OperatorActionRequest $request): OperatorActionResult;

    public function requeueToTransport(OperatorActionRequest $request, string $targetTransport): OperatorActionResult;
}
