<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Aggregate\StreamNameTranslator;
use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Store\Criteria\CriteriaBuilder;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StreamStore;
use Patchlevel\EventSourcing\Store\SubscriptionStore;
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
        private readonly HeadersSerializer $headersSerializer,
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
                'stream',
                null,
                InputOption::VALUE_REQUIRED,
                'Watch messages from a specific stream (e.g. "stream-*")',
            )
            ->addOption(
                'aggregate',
                null,
                InputOption::VALUE_REQUIRED,
                'Filter aggregate name',
            )
            ->addOption(
                'aggregate-id',
                null,
                InputOption::VALUE_REQUIRED,
                'Filter aggregate id',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new OutputStyle($input, $output);

        $sleep = InputHelper::positiveIntOrZero($input->getOption('sleep'));
        $stream = InputHelper::nullableString($input->getOption('stream'));
        $aggregate = InputHelper::nullableString($input->getOption('aggregate'));
        $aggregateId = InputHelper::nullableString($input->getOption('aggregate-id'));

        if ($stream !== null && ($aggregate !== null || $aggregateId !== null)) {
            $console->error('You can only provide stream or aggregate and aggregate-id');

            return 1;
        }

        $index = $this->currentIndex();

        if ($this->store instanceof SubscriptionStore) {
            $this->store->setupSubscription();
        }

        $criteriaBuilder = new CriteriaBuilder();

        if ($stream !== null) {
            $criteriaBuilder->streamName($stream);
        }

        if ($this->store instanceof StreamStore) {
            if ($aggregate !== null || $aggregateId !== null) {
                if ($aggregate === null || $aggregateId === null) {
                    $console->error('You must provide both aggregate and aggregate-id or none of them');

                    return 1;
                }

                $criteriaBuilder->streamName(StreamNameTranslator::streamName($aggregate, $aggregateId));
            }
        } else {
            $criteriaBuilder->aggregateName($aggregate);
            $criteriaBuilder->aggregateId($aggregateId);
        }

        $criteria = $criteriaBuilder->build();

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
        );

        $supportSubscription = $this->store instanceof SubscriptionStore && $this->store->supportSubscription();
        $worker->run($supportSubscription ? 0 : $sleep);

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
