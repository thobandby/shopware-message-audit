<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Core\Messenger\Subscriber;

use MessengerHistoryDashboard\Core\Messenger\Util\MessageUuidResolver;
use MessengerHistoryDashboard\Core\Service\MessageAuditWriter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

final class MessengerWorkerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageAuditWriter $writer
    ) {
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => 'onReceived',
            WorkerMessageHandledEvent::class => 'onHandled',
            WorkerMessageFailedEvent::class => 'onFailed',
        ];
    }

    public function onReceived(WorkerMessageReceivedEvent $event): void
    {
        $this->writer->onReceived(
            MessageUuidResolver::resolve($event->getEnvelope()),
            $event->getEnvelope()
        );
    }

    public function onHandled(WorkerMessageHandledEvent $event): void
    {
        $this->writer->onHandled(
            MessageUuidResolver::resolve($event->getEnvelope()),
            $event->getEnvelope()
        );
    }

    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        $this->writer->onFailed(
            MessageUuidResolver::resolve($event->getEnvelope()),
            $event->getEnvelope(),
            $event->getThrowable()
        );
    }
}
