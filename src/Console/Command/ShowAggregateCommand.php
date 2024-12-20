<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Store\Criteria\AggregateIdCriterion;
use Patchlevel\EventSourcing\Store\Criteria\AggregateNameCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StreamStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

use function array_values;
use function sprintf;

#[AsCommand(
    'event-sourcing:show-aggregate',
    'show events from one aggregate',
)]
final class ShowAggregateCommand extends Command
{
    public function __construct(
        private readonly Store $store,
        private readonly EventSerializer $eventSerializer,
        private readonly HeadersSerializer $headersSerializer,
        private readonly AggregateRootRegistry $aggregateRootRegistry,
        private readonly AggregateRootMetadataFactory $aggregateRootMetadataFactory = new AttributeAggregateRootMetadataFactory(),
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('aggregate', InputArgument::OPTIONAL, 'aggregate name')
            ->addArgument('id', InputArgument::OPTIONAL, 'aggregate id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new OutputStyle($input, $output);

        $aggregate = InputHelper::nullableString($input->getArgument('aggregate'));
        if ($aggregate === null) {
            $question = new ChoiceQuestion(
                'Choose the aggregate',
                array_values($this->aggregateRootRegistry->aggregateNames()),
                null,
            );

            $aggregate = InputHelper::string($console->askQuestion($question));
        }

        $id = InputHelper::nullableString($input->getArgument('id'));

        if (!$this->aggregateRootRegistry->hasAggregateName($aggregate)) {
            $console->error(sprintf('aggregate type "%s" not exists', $aggregate));

            return 1;
        }

        $streamName = null;

        if ($this->store instanceof StreamStore) {
            $aggregateClass = $this->aggregateRootRegistry->aggregateClass($aggregate);
            $streamName = $this->aggregateRootMetadataFactory->metadata($aggregateClass)->streamName($id);

            $stream = $this->store->load(
                new Criteria(
                    new StreamCriterion($streamName),
                ),
            );
        } else {
            if ($id === null) {
                $question = new Question('Enter the aggregate id');
                $id = InputHelper::string($console->askQuestion($question));
            }

            $stream = $this->store->load(
                new Criteria(
                    new AggregateNameCriterion($aggregate),
                    new AggregateIdCriterion($id),
                ),
            );
        }

        $hasMessage = false;
        foreach ($stream as $message) {
            $hasMessage = true;
            $console->message($this->eventSerializer, $this->headersSerializer, $message);
        }

        $stream->close();

        if ($hasMessage) {
            return 0;
        }

        if ($id !== null) {
            $console->error(sprintf('aggregate "%s" => "%s" not found', $aggregate, $id));
        } elseif ($streamName !== null) {
            $console->error(sprintf('aggregate for stream "%s" not found', $streamName));
        } else {
            $console->error('aggregate not found');
        }

        return 1;
    }
}
