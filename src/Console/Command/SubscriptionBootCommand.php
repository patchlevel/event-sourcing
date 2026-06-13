<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Closure;
use LogicException;
use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
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
    'event-sourcing:subscription:boot',
    'Catch up with the event store.',
)]
final class SubscriptionBootCommand extends SubscriptionCommand
{
    public function __construct(
        SubscriptionEngine $engine,
        private readonly EventDispatcherInterface|null $workerEventDispatcher = null,
    ) {
        parent::__construct($engine);
    }

    public function configure(): void
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
                1000,
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
                0,
            )
            ->addOption(
                'setup',
                null,
                InputOption::VALUE_NONE,
                'Setup new subscriptions',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runLimit = InputHelper::nullablePositiveInt($input->getOption('run-limit'));
        $messageLimit = InputHelper::nullablePositiveInt($input->getOption('message-limit'));
        $memoryLimit = InputHelper::nullableString($input->getOption('memory-limit'));
        $timeLimit = InputHelper::nullablePositiveInt($input->getOption('time-limit'));
        $sleep = InputHelper::positiveIntOrZero($input->getOption('sleep'));
        $setup = InputHelper::bool($input->getOption('setup'));

        $criteria = $this->subscriptionEngineCriteria($input);
        $criteria = $this->resolveCriteriaIntoCriteriaWithOnlyIds($criteria);

        if ($setup) {
            $this->engine->execute(new Setup(
                $criteria->ids,
                $criteria->groups,
            ));
        }

        $logger = new ConsoleLogger($output);
        $finished = false;

        $worker = DefaultWorker::create(
            function (Closure $stop) use ($criteria, $messageLimit, &$finished): void {
                $result = $this->engine->execute(new Boot(
                    $criteria->ids,
                    $criteria->groups,
                    $messageLimit,
                ));

                if (!$result instanceof ProcessedResult) {
                    throw new LogicException('Expected ProcessedResult');
                }

                if (!$result->finished) {
                    return;
                }

                $finished = true;
                $stop();
            },
            [
                'runLimit' => $runLimit,
                'memoryLimit' => $memoryLimit,
                'timeLimit' => $timeLimit,
            ],
            $logger,
            $this->workerEventDispatcher,
        );

        $worker->run($sleep);

        return $finished ? 0 : 1;
    }
}
