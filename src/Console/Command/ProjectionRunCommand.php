<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\Worker\DefaultWorker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:projection:run',
    'Run the active projections',
)]
final class ProjectionRunCommand extends ProjectionCommand
{
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
                'How much memory consumption should the worker be terminated',
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
                'How much time should elapse before the next job is executed in microseconds',
                1000,
            )
            ->addOption(
                'throw-by-error',
                null,
                InputOption::VALUE_NONE,
                'throw exception by error',
            )
            ->addOption(
                'rebuild',
                null,
                InputOption::VALUE_NONE,
                'rebuild (remove & boot) projections before run',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runLimit = InputHelper::nullablePositiveInt($input->getOption('run-limit'));
        $messageLimit = InputHelper::nullablePositiveInt($input->getOption('message-limit'));
        $memoryLimit = InputHelper::nullableString($input->getOption('memory-limit'));
        $timeLimit = InputHelper::nullablePositiveInt($input->getOption('time-limit'));
        $sleep = InputHelper::positiveIntOrZero($input->getOption('sleep'));
        $throwByError = InputHelper::bool($input->getOption('throw-by-error'));
        $rebuild = InputHelper::bool($input->getOption('rebuild'));

        $criteria = $this->projectionCriteria($input);

        $logger = new ConsoleLogger($output);

        $worker = DefaultWorker::create(
            function () use ($criteria, $messageLimit, $throwByError): void {
                $this->projectionist->run($criteria, $messageLimit, $throwByError);
            },
            [
                'runLimit' => $runLimit,
                'memoryLimit' => $memoryLimit,
                'timeLimit' => $timeLimit,
            ],
            $logger,
        );

        if ($rebuild) {
            $this->projectionist->remove($criteria);
            $this->projectionist->boot($criteria, null, $throwByError);
        }

        $worker->run($sleep);

        return 0;
    }
}
