<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\InvalidArgumentGiven;
use Patchlevel\EventSourcing\Projection\Projectionist\Listener\ThrowErrorListener;
use Patchlevel\EventSourcing\Projection\Projectionist\Projectionist;
use Patchlevel\Worker\DefaultWorker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    'event-sourcing:projectionist:dev-run',
    'Run the active projectors',
)]
final class ProjectionistDevRunCommand extends ProjectionistCommand
{
    public function __construct(
        Projectionist $projectionist,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct($projectionist);
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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runLimit = InputHelper::nullablePositivInt($input->getOption('run-limit'));
        $messageLimit = InputHelper::nullablePositivInt($input->getOption('message-limit'));
        $memoryLimit = InputHelper::nullableString($input->getOption('memory-limit'));
        $timeLimit = InputHelper::nullablePositivInt($input->getOption('time-limit'));
        $sleep = InputHelper::int($input->getOption('sleep'));
        $criteria = $this->projectionCriteria($input);

        $logger = new ConsoleLogger($output);

        $this->eventDispatcher->addSubscriber(new ThrowErrorListener());

        $worker = DefaultWorker::create(
            function () use ($criteria, $messageLimit): void {
                $this->projectionist->run($criteria, $messageLimit);
            },
            [
                'runLimit' => $runLimit,
                'memoryLimit' => $memoryLimit,
                'timeLimit' => $timeLimit,
            ],
            $logger,
        );

        if ($sleep < 0) {
            throw new InvalidArgumentGiven($sleep, '0|positive-int');
        }

        $this->projectionist->remove($criteria);
        $this->projectionist->boot($criteria);
        $worker->run($sleep);

        return 0;
    }
}
