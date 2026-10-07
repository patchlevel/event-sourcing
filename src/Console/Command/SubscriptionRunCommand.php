<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Closure;
use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Store\ListenableStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Remove;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\Worker\DefaultWorker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    'event-sourcing:subscription:run',
    'Run the active subscriptions',
)]
final class SubscriptionRunCommand extends SubscriptionCommand
{
    public function __construct(
        SubscriptionEngine $engine,
        private readonly Store $store,
        private readonly EventDispatcherInterface|null $workerEventDispatcher = null,
    ) {
        parent::__construct($engine);
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption(
                'run-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'The maximum number of runs this command should execute',
            )
            ->addOption(
                'message-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'How many messages should be consumed in one run',
                100,
            )
            ->addOption(
                'memory-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'How much memory consumption should the worker be terminated (e.g. 250MB)',
            )
            ->addOption(
                'time-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'What is the maximum time the worker can run in seconds',
            )
            ->addOption(
                'sleep',
                null,
                InputOption::VALUE_REQUIRED,
                'How much time should elapse before the next job is executed in milliseconds',
                1000,
            )
            ->addOption(
                'restart-signal-file',
                null,
                InputOption::VALUE_REQUIRED,
                'Stop the worker when this file is touched after it has started (e.g. on deployment)',
            )
            ->addOption(
                'heartbeat-file',
                null,
                InputOption::VALUE_REQUIRED,
                'Touch this file after every run, e.g. for liveness probes',
            )
            ->addOption(
                'rebuild',
                null,
                InputOption::VALUE_NONE,
                'rebuild (remove & boot) subscriptions before run',
            )
            ->addOption(
                'stop-when-finished',
                null,
                InputOption::VALUE_NONE,
                'Stop the worker as soon as all messages are processed',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runLimit = InputHelper::nullablePositiveInt($input->getOption('run-limit'));
        $messageLimit = InputHelper::nullablePositiveInt($input->getOption('message-limit'));
        $memoryLimit = InputHelper::nullableString($input->getOption('memory-limit'));
        $timeLimit = InputHelper::nullablePositiveInt($input->getOption('time-limit'));
        $sleep = InputHelper::positiveIntOrZero($input->getOption('sleep'));
        $rebuild = InputHelper::bool($input->getOption('rebuild'));
        $stopWhenFinished = InputHelper::bool($input->getOption('stop-when-finished'));
        $restartSignalFile = InputHelper::nullableString($input->getOption('restart-signal-file'));
        $heartbeatFile = InputHelper::nullableString($input->getOption('heartbeat-file'));

        $criteria = $this->subscriptionEngineCriteria($input);
        $criteria = $this->resolveCriteriaIntoCriteriaWithOnlyIds($criteria);

        $logger = new ConsoleLogger($output);

        $worker = DefaultWorker::create(
            function (Closure $stop) use ($criteria, $messageLimit, $sleep, $stopWhenFinished): bool {
                $result = $this->engine->execute(new Run($criteria->ids, $criteria->groups, $messageLimit));

                if ($result instanceof ProcessedResult && !$result->finished) {
                    return true;
                }

                if ($stopWhenFinished) {
                    $stop();

                    return false;
                }

                if ($this->store instanceof ListenableStore) {
                    $this->store->wait($sleep);
                }

                return false;
            },
            [
                'runLimit' => $runLimit,
                'memoryLimit' => $memoryLimit,
                'timeLimit' => $timeLimit,
                'restartSignalFile' => $restartSignalFile,
                'heartbeatFile' => $heartbeatFile,
            ],
            $logger,
            $this->workerEventDispatcher,
        );

        if ($rebuild) {
            $this->engine->execute(new Remove($criteria->ids, $criteria->groups));
            $this->engine->execute(new Boot($criteria->ids, $criteria->groups));
        }

        $worker->run($this->store instanceof ListenableStore ? 0 : $sleep);

        return 0;
    }
}
