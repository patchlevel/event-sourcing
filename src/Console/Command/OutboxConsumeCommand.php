<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Outbox\OutboxProcessor;
use Patchlevel\Worker\DefaultWorker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:outbox:consume',
    'published the messages from the outbox store',
)]
final class OutboxConsumeCommand extends Command
{
    public function __construct(
        private readonly OutboxProcessor $processor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'message-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'How many messages should be consumed in one run',
                100,
            )
            ->addOption(
                'run-limit',
                null,
                InputOption::VALUE_OPTIONAL,
                'The maximum number of runs this command should execute',
                1,
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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $messageLimit = InputHelper::int($input->getOption('message-limit'));
        $runLimit = InputHelper::nullablePositiveInt($input->getOption('run-limit'));
        $memoryLimit = InputHelper::nullableString($input->getOption('memory-limit'));
        $timeLimit = InputHelper::nullablePositiveInt($input->getOption('time-limit'));
        $sleep = InputHelper::positiveIntOrZero($input->getOption('sleep'));

        $logger = new ConsoleLogger($output);

        $worker = DefaultWorker::create(
            function () use ($messageLimit): void {
                $this->processor->process($messageLimit);
            },
            [
                'runLimit' => $runLimit,
                'memoryLimit' => $memoryLimit,
                'timeLimit' => $timeLimit,
            ],
            $logger,
        );

        $worker->run($sleep);

        return 0;
    }
}
