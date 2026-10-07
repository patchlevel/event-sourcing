<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Console\Tui\ActionRunner\ActionRunner;
use Patchlevel\EventSourcing\Console\Tui\ActionRunner\InProcessActionRunner;
use Patchlevel\EventSourcing\Console\Tui\ActionRunner\ProcessActionRunner;
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

use function array_filter;
use function class_exists;
use function is_array;
use function is_file;
use function is_numeric;
use function is_string;
use function max;

use const PHP_BINARY;

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
                'in-process',
                null,
                InputOption::VALUE_NONE,
                'Execute the actions in the dashboard process instead of starting the subscription commands',
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
            $this->actionRunner($input),
            $this->terminal,
            $this->clock,
        );

        $dashboard->run();

        return 0;
    }

    /**
     * Starts the actions as separate processes with the subscription commands of this application,
     * so the dashboard keeps rendering. Falls back to the engine if they are not available.
     */
    private function actionRunner(InputInterface $input): ActionRunner
    {
        $messageLimit = InputHelper::nullablePositiveInt($input->getOption('message-limit'));
        $application = $this->getApplication();
        $argv = $_SERVER['argv'] ?? null;
        $script = is_array($argv) ? $argv[0] ?? null : null;

        if (
            InputHelper::bool($input->getOption('in-process'))
            || $application === null
            || !is_string($script)
            || !is_file($script)
            || array_filter(ProcessActionRunner::COMMANDS, static fn (string $name) => !$application->has($name)) !== []
        ) {
            return new InProcessActionRunner($this->engine, $messageLimit);
        }

        $console = [PHP_BINARY, $script];
        $env = $input->getParameterOption(['--env', '-e'], null, true);

        if (is_string($env)) {
            $console[] = '--env=' . $env;
        }

        return new ProcessActionRunner($console, $messageLimit);
    }
}
