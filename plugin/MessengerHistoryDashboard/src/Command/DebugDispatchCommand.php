<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Command;

use Doctrine\DBAL\Connection;
use MessengerHistoryDashboard\Core\Message\SampleSuccessMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

#[AsCommand(
    name: 'mh:debug:dispatch',
    description: 'Debugs Messenger sender resolution and Doctrine queue writes for Status Audit'
)]
final class DebugDispatchCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly SendersLocatorInterface $sendersLocator,
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        unset($input);

        $reference = 'debug-' . bin2hex(random_bytes(6));
        $message = new SampleSuccessMessage($reference, 'Debug probe');
        $envelope = new Envelope($message);

        $output->writeln('<info>Dispatch probe</info>');
        $output->writeln('message_class: ' . $message::class);
        $output->writeln('reference: ' . $reference);

        $resolvedSenders = [];
        foreach ($this->sendersLocator->getSenders($envelope) as $alias => $sender) {
            $resolvedSenders[] = [
                'alias' => $alias,
                'class' => $sender::class,
            ];
        }

        $output->writeln('resolved_senders: ' . json_encode($resolvedSenders, JSON_THROW_ON_ERROR));

        $queueCountBefore = $this->countQueueMessages();
        $auditCountBefore = $this->countAuditMessages();

        $returnedEnvelope = $this->messageBus->dispatch($message);

        $queueCountAfter = $this->countQueueMessages();
        $auditCountAfter = $this->countAuditMessages();

        $output->writeln('queue_count_before: ' . $queueCountBefore);
        $output->writeln('queue_count_after: ' . $queueCountAfter);
        $output->writeln('audit_count_before: ' . $auditCountBefore);
        $output->writeln('audit_count_after: ' . $auditCountAfter);

        $sentStamps = $returnedEnvelope->all(SentStamp::class);
        $transportMessageIdStamps = $returnedEnvelope->all(TransportMessageIdStamp::class);

        $output->writeln('sent_stamp_count: ' . \count($sentStamps));
        foreach ($sentStamps as $index => $stamp) {
            $output->writeln(\sprintf(
                'sent_stamp_%d: senderClass=%s senderAlias=%s',
                $index,
                $stamp->getSenderClass(),
                $stamp->getSenderAlias() ?? '(none)'
            ));
        }

        $output->writeln('transport_message_id_stamp_count: ' . \count($transportMessageIdStamps));
        foreach ($transportMessageIdStamps as $index => $stamp) {
            $output->writeln(\sprintf(
                'transport_message_id_stamp_%d: %s',
                $index,
                $stamp->getId()
            ));
        }

        $latestQueueRows = $this->connection->fetchAllAssociative(
            'SELECT id, queue_name, available_at, delivered_at
             FROM messenger_messages
             ORDER BY id DESC
             LIMIT 5'
        );
        $output->writeln('latest_queue_rows: ' . json_encode($latestQueueRows, JSON_THROW_ON_ERROR));

        $latestAuditRows = $this->connection->fetchAllAssociative(
            'SELECT id, message_class, status, business_reference, transport_name
             FROM mh_message
             WHERE business_reference = :reference
             ORDER BY created_at DESC',
            ['reference' => $reference]
        );
        $output->writeln('audit_rows_for_reference: ' . json_encode($latestAuditRows, JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }

    private function countQueueMessages(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM messenger_messages');
    }

    private function countAuditMessages(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mh_message');
    }
}
