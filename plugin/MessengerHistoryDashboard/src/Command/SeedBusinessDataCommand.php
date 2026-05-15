<?php

declare(strict_types=1);

namespace MessengerHistoryDashboard\Command;

use MessengerHistoryDashboard\Core\Repository\BusinessOrderSeedRepository;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Demodata\DemodataRequest;
use Shopware\Core\Framework\Demodata\DemodataService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

#[AsCommand(
    name: 'mh:seed:business',
    description: 'Generates fresh demo orders and applies real Shopware order, payment, and delivery state changes'
)]
final class SeedBusinessDataCommand extends Command
{
    public function __construct(
        private readonly BusinessOrderSeedRepository $businessOrderSeedRepository,
        private readonly DemodataService $demodataService,
        private readonly OrderService $orderService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'How many recent orders should be processed', '12');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = max(1, (int) $input->getOption('limit'));
        $context = Context::createCLIContext();
        $parameters = new ParameterBag(['sendMail' => false]);
        $lastAutoIncrement = $this->businessOrderSeedRepository->findLatestAutoIncrement();

        $this->generateFreshOrders($limit, $context, $output);
        $orders = $this->businessOrderSeedRepository->findCreatedAfterAutoIncrement($lastAutoIncrement, $limit);

        if ($orders === []) {
            $output->writeln('<comment>No fresh orders were generated for business state simulation.</comment>');

            return Command::FAILURE;
        }

        $output->writeln(\sprintf('Processing %d fresh orders for business activity.', \count($orders)));

        foreach ($orders as $index => $order) {
            $pattern = $index % 4;
            $output->writeln(\sprintf('order=%s pattern=%d', $order['order_number'], $pattern));

            match ($pattern) {
                0 => $this->applySuccessfulFlow($order, $parameters, $context, $output),
                1 => $this->applyInProgressFlow($order, $parameters, $context, $output),
                2 => $this->applyFailureFlow($order, $parameters, $context, $output),
                default => $this->applyReminderFlow($order, $parameters, $context, $output),
            };
        }

        $output->writeln('<info>Business data simulation completed.</info>');

        return Command::SUCCESS;
    }

    private function generateFreshOrders(int $limit, Context $context, OutputInterface $output): void
    {
        $output->writeln(\sprintf('Generating %d fresh demo orders.', $limit));

        $request = new DemodataRequest();
        $request->add(OrderDefinition::class, $limit);

        $this->demodataService->generate($request, $context, null);
    }

    /**
     * @param array{id: string, order_number: string, transaction_id: string|null, delivery_id: string|null} $order
     */
    private function applySuccessfulFlow(array $order, ParameterBag $parameters, Context $context, OutputInterface $output): void
    {
        $this->transitionTransaction($order['transaction_id'], 'process', $parameters, $context, $output);
        $this->transitionTransaction($order['transaction_id'], 'paid', $parameters, $context, $output);
        $this->transitionDelivery($order['delivery_id'], 'ship', $parameters, $context, $output);
        $this->transitionOrder($order['id'], 'process', $parameters, $context, $output);
        $this->transitionOrder($order['id'], 'complete', $parameters, $context, $output);
    }

    /**
     * @param array{id: string, order_number: string, transaction_id: string|null, delivery_id: string|null} $order
     */
    private function applyInProgressFlow(array $order, ParameterBag $parameters, Context $context, OutputInterface $output): void
    {
        $this->transitionTransaction($order['transaction_id'], 'process', $parameters, $context, $output);
        $this->transitionDelivery($order['delivery_id'], 'ship_partially', $parameters, $context, $output);
        $this->transitionOrder($order['id'], 'process', $parameters, $context, $output);
    }

    /**
     * @param array{id: string, order_number: string, transaction_id: string|null, delivery_id: string|null} $order
     */
    private function applyFailureFlow(array $order, ParameterBag $parameters, Context $context, OutputInterface $output): void
    {
        $this->transitionTransaction($order['transaction_id'], 'fail', $parameters, $context, $output);
        $this->transitionOrder($order['id'], 'cancel', $parameters, $context, $output);
    }

    /**
     * @param array{id: string, order_number: string, transaction_id: string|null, delivery_id: string|null} $order
     */
    private function applyReminderFlow(array $order, ParameterBag $parameters, Context $context, OutputInterface $output): void
    {
        $this->transitionTransaction($order['transaction_id'], 'remind', $parameters, $context, $output);
        $this->transitionOrder($order['id'], 'process', $parameters, $context, $output);
    }

    private function transitionOrder(
        string $orderId,
        string $transition,
        ParameterBag $parameters,
        Context $context,
        OutputInterface $output
    ): void {
        try {
            $state = $this->orderService->orderStateTransition($orderId, $transition, $parameters, $context);
            $output->writeln(\sprintf('  order:%s -> %s', $transition, $state->getTechnicalName()));
        } catch (\Throwable $exception) {
            $output->writeln(\sprintf('  order:%s skipped (%s)', $transition, $exception->getMessage()));
        }
    }

    private function transitionTransaction(
        ?string $transactionId,
        string $transition,
        ParameterBag $parameters,
        Context $context,
        OutputInterface $output
    ): void {
        if ($transactionId === null) {
            $output->writeln(\sprintf('  transaction:%s skipped (missing transaction)', $transition));

            return;
        }

        try {
            $state = $this->orderService->orderTransactionStateTransition($transactionId, $transition, $parameters, $context);
            $output->writeln(\sprintf('  transaction:%s -> %s', $transition, $state->getTechnicalName()));
        } catch (\Throwable $exception) {
            $output->writeln(\sprintf('  transaction:%s skipped (%s)', $transition, $exception->getMessage()));
        }
    }

    private function transitionDelivery(
        ?string $deliveryId,
        string $transition,
        ParameterBag $parameters,
        Context $context,
        OutputInterface $output
    ): void {
        if ($deliveryId === null) {
            $output->writeln(\sprintf('  delivery:%s skipped (missing delivery)', $transition));

            return;
        }

        try {
            $state = $this->orderService->orderDeliveryStateTransition($deliveryId, $transition, $parameters, $context);
            $output->writeln(\sprintf('  delivery:%s -> %s', $transition, $state->getTechnicalName()));
        } catch (\Throwable $exception) {
            $output->writeln(\sprintf('  delivery:%s skipped (%s)', $transition, $exception->getMessage()));
        }
    }
}
