<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Store\Criteria\CriteriaBuilder;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\SubscriptionStore;
use Patchlevel\Worker\DefaultWorker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    'event-sourcing:watch',
    'live stream of all aggregate events',
)]
final class WatchCommand extends Command
{
    public function __construct(
        private readonly Store $store,
        private readonly EventSerializer $eventSerializer,
        private readonly HeadersSerializer $headersSerializer,
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
                'The maximum number of runs this command should execute',
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
                'stream',
                null,
                InputOption::VALUE_REQUIRED,
                'Watch messages from a specific stream (e.g. "stream-*")',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new OutputStyle($input, $output);

        $runLimit = InputHelper::nullablePositiveInt($input->getOption('run-limit'));
        $memoryLimit = InputHelper::nullableString($input->getOption('memory-limit'));
        $timeLimit = InputHelper::nullablePositiveInt($input->getOption('time-limit'));
        $sleep = InputHelper::positiveIntOrZero($input->getOption('sleep'));
        $stream = InputHelper::nullableString($input->getOption('stream'));

        $index = $this->currentIndex();

        if ($this->store instanceof SubscriptionStore) {
            $this->store->setupSubscription();
        }

        $criteriaBuilder = new CriteriaBuilder();
        $criteriaBuilder->streamName($stream);
        $criteria = $criteriaBuilder->build();

        $errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $logger = new ConsoleLogger($errOutput);

        $worker = DefaultWorker::create(
            function () use ($console, &$index, $criteria, $sleep): void {
                $stream = $this->store->load(
                    $criteria->add(new FromIndexCriterion($index)),
                );

                foreach ($stream as $message) {
                    $console->message($this->eventSerializer, $this->headersSerializer, $message);

                    /** @var int $index */
                    $index = $stream->index();
                }

                $stream->close();

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

        $supportSubscription = $this->store instanceof SubscriptionStore && $this->store->supportSubscription();
        $worker->run($supportSubscription ? 0 : $sleep);

        return Command::SUCCESS;
    }

    private function currentIndex(): int
    {
        $stream = $this->store->load(
            limit: 1,
            backwards: true,
        );

        $index = $stream->index() ?? 0;

        $stream->close();

        return $index;
    }
}
