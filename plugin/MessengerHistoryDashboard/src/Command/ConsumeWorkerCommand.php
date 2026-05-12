<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'mh:worker:consume',
    description: 'Starts the standard Shopware Messenger worker for Messenger Audit transports'
)]
final class ConsumeWorkerCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument(
                'receivers',
                InputArgument::IS_ARRAY,
                'Receivers to consume. Defaults to the standard Shopware transports used by Messenger Audit.'
            )
            ->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'Maximum runtime in seconds.')
            ->addOption('memory-limit', null, InputOption::VALUE_REQUIRED, 'Maximum memory usage before stopping.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of messages to consume.')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Sleep duration in microseconds when no messages are available.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $application = $this->getApplication();

        if ($application === null) {
            $output->writeln('<error>Console application is not available.</error>');

            return Command::FAILURE;
        }

        $receivers = $input->getArgument('receivers');
        $receivers = \is_array($receivers) && $receivers !== [] ? $receivers : ['async', 'low_priority'];

        $arguments = [
            'command' => 'messenger:consume',
            'receivers' => $receivers,
        ];

        foreach (['time-limit', 'memory-limit', 'limit', 'sleep'] as $option) {
            $value = $input->getOption($option);

            if (\is_string($value) && trim($value) !== '') {
                $arguments['--' . $option] = trim($value);
            }
        }

        $forwardInput = new ArrayInput($arguments);
        $forwardInput->setInteractive($input->isInteractive());

        return $application->find('messenger:consume')->run($forwardInput, $output);
    }
}
