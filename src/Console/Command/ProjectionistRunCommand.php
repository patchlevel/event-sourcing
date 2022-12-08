<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\InvalidArgumentGiven;
use Patchlevel\EventSourcing\Console\Worker\Bytes;
use Patchlevel\EventSourcing\Console\Worker\DefaultWorker;
use Patchlevel\EventSourcing\Console\Worker\Listener\StopWorkerOnIterationLimitListener;
use Patchlevel\EventSourcing\Console\Worker\Listener\StopWorkerOnMemoryLimitListener;
use Patchlevel\EventSourcing\Console\Worker\Listener\StopWorkerOnSigtermSignalListener;
use Patchlevel\EventSourcing\Console\Worker\Listener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[AsCommand(
    'event-sourcing:projectionist:run',
    'Run the active projectors'
)]
final class ProjectionistRunCommand extends ProjectionistCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption(
                'run-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'The maximum number of runs this command should execute'
            )
            ->addOption(
                'message-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'How many messages should be consumed in one run',
                100
            )
            ->addOption(
                'memory-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'How much memory consumption should the worker be terminated'
            )
            ->addOption(
                'time-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'What is the maximum time the worker can run in seconds'
            )
            ->addOption(
                'sleep',
                null,
                InputOption::VALUE_REQUIRED,
                'How much time should elapse before the next job is executed in microseconds',
                1000
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runLimit = InputHelper::nullableInt($input->getOption('run-limit'));
        $messageLimit = InputHelper::nullableInt($input->getOption('message-limit'));
        $memoryLimit = InputHelper::nullableString($input->getOption('memory-limit'));
        $timeLimit = InputHelper::nullableInt($input->getOption('time-limit'));
        $sleep = InputHelper::int($input->getOption('sleep'));
        $criteria = $this->projectionCriteria($input);

        $logger = new ConsoleLogger($output);

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnSigtermSignalListener($logger));

        if ($runLimit) {
            if ($runLimit <= 0) {
                throw new InvalidArgumentGiven($runLimit, 'null|positive-int');
            }

            $eventDispatcher->addSubscriber(new StopWorkerOnIterationLimitListener($runLimit, $logger));
        }

        if ($memoryLimit) {
            $eventDispatcher->addSubscriber(
                new StopWorkerOnMemoryLimitListener(Bytes::parseFromString($memoryLimit), $logger)
            );
        }

        if ($timeLimit) {
            if ($timeLimit <= 0) {
                throw new InvalidArgumentGiven($timeLimit, 'null|positive-int');
            }

            $eventDispatcher->addSubscriber(new StopWorkerOnTimeLimitListener($timeLimit, $logger));
        }

        if ($messageLimit !== null && $messageLimit <= 0) {
            throw new InvalidArgumentGiven($messageLimit, 'null|positive-int');
        }

        $worker = new DefaultWorker(
            function () use ($criteria, $messageLimit): void {
                $this->projectionist->run($criteria, $messageLimit);
            },
            $eventDispatcher,
            $logger
        );

        if ($sleep < 0) {
            throw new InvalidArgumentGiven($sleep, '0|positive-int');
        }

        $worker->run($sleep);

        return 0;
    }
}
