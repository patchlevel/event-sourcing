<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\Worker\DefaultWorker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:subscription:run',
    'Run the active subscriptions',
)]
final class SubscriptionRunCommand extends SubscriptionCommand
{
    public function __construct(
        SubscriptionEngine $engine,
        private readonly Store $store,
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
                'rebuild',
                null,
                InputOption::VALUE_NONE,
                'rebuild (remove & boot) subscriptions before run',
            )
            ->addOption(
                'skip-subscription-setup',
                null,
                InputOption::VALUE_NONE,
                'skip subscription setup',
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
        $skipSubscriptionSetup = InputHelper::bool($input->getOption('skip-subscription-setup'));

        $criteria = $this->subscriptionEngineCriteria($input);
        $criteria = $this->resolveCriteriaIntoCriteriaWithOnlyIds($criteria);

        if ($this->store instanceof SubscriptionStore && !$skipSubscriptionSetup) {
            $this->store->setupSubscription();
        }

        $logger = new ConsoleLogger($output);

        $worker = DefaultWorker::create(
            function () use ($criteria, $messageLimit, $sleep): void {
                $this->engine->run($criteria, $messageLimit);

                if (!$this->store instanceof SubscriptionStore) {
                    return;
                }

                $this->store->wait($sleep);
            },
            [
                'runLimit' => $runLimit,
                'memoryLimit' => $memoryLimit,
                'timeLimit' => $timeLimit,
            ],
            $logger,
        );

        if ($rebuild) {
            $this->engine->remove($criteria);
            $this->engine->boot($criteria);
        }

        $supportSubscription = $this->store instanceof SubscriptionStore && $this->store->supportSubscription();
        $worker->run($supportSubscription ? 0 : $sleep);

        return 0;
    }
}
