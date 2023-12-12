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
use Patchlevel\EventSourcing\Outbox\OutboxConsumer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[AsCommand(
    'event-sourcing:outbox:consume',
    'published the messages from the outbox store',
)]
final class OutboxConsumeCommand extends Command
{
    public function __construct(
        private readonly OutboxConsumer $consumer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_REQUIRED,
                'How many messages should be consumed in one run (deprecated: use "message-limit" option)',
            )
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
        $legacyLimit = InputHelper::nullableInt($input->getOption('limit'));
        $messageLimit = InputHelper::int($input->getOption('message-limit'));
        $runLimit = InputHelper::nullableInt($input->getOption('run-limit'));
        $memoryLimit = InputHelper::nullableString($input->getOption('memory-limit'));
        $timeLimit = InputHelper::nullableInt($input->getOption('time-limit'));
        $sleep = InputHelper::int($input->getOption('sleep'));

        // legacy limit option to message-limit
        if ($legacyLimit !== null) {
            $messageLimit = $legacyLimit;
        }

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
            function () use ($messageLimit): void {
                $this->consumer->consume($messageLimit);
            },
            $eventDispatcher,
            $logger,
        );

        if ($sleep < 0) {
            throw new InvalidArgumentGiven($sleep, '0|positive-int');
        }

        $worker->run($sleep);

        return 0;
    }
}
