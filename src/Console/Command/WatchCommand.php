<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Store\Criteria;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\Worker\DefaultWorker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'sleep',
                null,
                InputOption::VALUE_REQUIRED,
                'How much time should elapse before the next job is executed in milliseconds',
                1000,
            )
            ->addOption(
                'aggregate',
                null,
                InputOption::VALUE_REQUIRED,
                'filter aggregate name',
            )
            ->addOption(
                'aggregate-id',
                null,
                InputOption::VALUE_REQUIRED,
                'filter aggregate id',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new OutputStyle($input, $output);

        $sleep = InputHelper::positiveIntOrZero($input->getOption('sleep'));
        $aggregate = InputHelper::nullableString($input->getOption('aggregate'));
        $aggregateId = InputHelper::nullableString($input->getOption('aggregate-id'));

        $index = $this->currentIndex();

        $worker = DefaultWorker::create(
            function () use ($console, &$index, $aggregate, $aggregateId): void {
                $stream = $this->store->load(
                    new Criteria(
                        $aggregate,
                        $aggregateId,
                        $index,
                    ),
                );

                foreach ($stream as $message) {
                    $console->message($this->eventSerializer, $message);
                    $index = $stream->index();
                }

                $stream->close();
            },
            [],
        );

        $worker->run($sleep);

        return 0;
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
