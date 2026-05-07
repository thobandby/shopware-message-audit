<?php declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Messenger\Middleware;

use MessengerHistoryDashboard\Core\Messenger\Stamp\MessageUuidStamp;
use MessengerHistoryDashboard\Core\Messenger\Util\MessageUuidResolver;
use MessengerHistoryDashboard\Core\Service\MessageAuditWriter;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final class DispatchAuditMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly MessageAuditWriter $writer
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $uuid = MessageUuidResolver::resolve($envelope);

        if (!$envelope->last(MessageUuidStamp::class)) {
            $envelope = $envelope->with(new MessageUuidStamp($uuid));
        }

        $this->writer->onDispatched($uuid, $envelope->getMessage(), $envelope);

        return $stack->next()->handle($envelope, $stack);
    }
}
