<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Console\Tui\SubscriptionDashboard;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Tui\Terminal\TerminalInterface;
use Symfony\Component\Tui\Tui;

use function class_exists;
use function is_numeric;
use function max;

/** @experimental */
#[AsCommand(
    'event-sourcing:subscription:dashboard',
    'Interactive dashboard to monitor and manage the subscriptions',
)]
final class SubscriptionDashboardCommand extends SubscriptionCommand
{
    public function __construct(
        SubscriptionEngine $engine,
        private readonly Store|null $store = null,
        private readonly ClockInterface|null $clock = null,
        private readonly TerminalInterface|null $terminal = null,
    ) {
        parent::__construct($engine);
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption(
                'refresh',
                null,
                InputOption::VALUE_REQUIRED,
                'How often the subscriptions are reloaded in seconds',
                2,
            )
            ->addOption(
                'message-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'How many messages run, boot and rebuild process at once',
                1000,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new OutputStyle($input, $output);

        if (!class_exists(Tui::class)) {
            $io->error('The dashboard requires the symfony/tui component (PHP 8.4+). Try running "composer require symfony/tui".');

            return 1;
        }

        if (!$input->isInteractive() && $this->terminal === null) {
            $io->error('The dashboard requires an interactive terminal.');

            return 2;
        }

        $refresh = $input->getOption('refresh');

        $dashboard = new SubscriptionDashboard(
            $this->engine,
            $this->store,
            $this->subscriptionEngineCriteria($input),
            is_numeric($refresh) ? max(0.1, (float)$refresh) : 2.0,
            InputHelper::nullablePositiveInt($input->getOption('message-limit')),
            $this->terminal,
            $this->clock,
        );

        $dashboard->run();

        return 0;
    }
}
