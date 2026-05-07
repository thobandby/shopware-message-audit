<?php declare(strict_types=1);

namespace MessengerHistoryDashboard\Command;

use MessengerHistoryDashboard\Core\Message\SampleFailureMessage;
use MessengerHistoryDashboard\Core\Message\SampleSuccessMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'mh:demo:seed', description: 'Dispatches demo messages for Messenger Audit')]
final class SeedDemoCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->messageBus->dispatch(new SampleSuccessMessage('demo-success-1', 'Demo success'));
        $this->messageBus->dispatch(new SampleSuccessMessage('demo-success-2', 'Demo success'));

        try {
            $this->messageBus->dispatch(new SampleFailureMessage('demo-failure-1', 'Demo failure'));
        } catch (\Throwable $e) {
            $output->writeln('Expected failure: ' . $e->getMessage());
        }

        $output->writeln('Demo messages dispatched.');

        return Command::SUCCESS;
    }
}
