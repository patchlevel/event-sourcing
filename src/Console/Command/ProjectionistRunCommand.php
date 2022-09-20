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
use Patchlevel\EventSourcing\Projection\Projectionist;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[AsCommand(
    'event-sourcing:projectionist:run',
    'TODO'
)]
final class ProjectionistRunCommand extends Command
{
    public function __construct(
        private readonly Projectionist $projectionist
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'run-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'The maximum number of runs this command should execute'
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
        $memoryLimit = InputHelper::nullableString($input->getOption('memory-limit'));
        $timeLimit = InputHelper::nullableInt($input->getOption('time-limit'));
        $sleep = InputHelper::int($input->getOption('sleep'));

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
            $eventDispatcher->addSubscriber(new StopWorkerOnMemoryLimitListener(Bytes::parseFromString($memoryLimit), $logger));
        }

        if ($timeLimit) {
            if ($timeLimit <= 0) {
                throw new InvalidArgumentGiven($timeLimit, 'null|positive-int');
            }

            $eventDispatcher->addSubscriber(new StopWorkerOnTimeLimitListener($timeLimit, $logger));
        }

        $worker = new DefaultWorker(
            function (): void {
                $this->projectionist->run();
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
