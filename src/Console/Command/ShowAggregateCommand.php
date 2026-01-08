<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Message\Serializer\HeadersSerializer;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\MissingAggregateIdForStreamName;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Store;
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

        if (!$this->aggregateRootRegistry->hasAggregateName($aggregate)) {
            $console->error(sprintf('aggregate type "%s" not exists', $aggregate));

            return 1;
        }

        $id = InputHelper::nullableString($input->getArgument('id'));

        $aggregateClass = $this->aggregateRootRegistry->aggregateClass($aggregate);

        $metadata = $this->aggregateRootMetadataFactory->metadata($aggregateClass);

        try {
            $streamName = $metadata->streamName($id);
        } catch (MissingAggregateIdForStreamName) {
            $question = new Question('Enter the aggregate id');
            $id = InputHelper::string($console->askQuestion($question));

            $streamName = $metadata->streamName($id);
        }

        $stream = $this->store->load(
            new Criteria(
                new StreamCriterion($streamName),
            ),
        );

        $hasMessage = false;
        foreach ($stream as $message) {
            $hasMessage = true;
            $console->message($this->eventSerializer, $this->headersSerializer, $message);
        }

        $stream->close();

        if ($hasMessage) {
            return 0;
        }

        $aggregateIdentifier = $id !== null
            ? sprintf('aggregate "%s" with id "%s"', $aggregate, $id)
            : sprintf('aggregate "%s"', $aggregate);

        $console->error(sprintf('%s in stream "%s" not found', $aggregateIdentifier, $streamName));

        return 1;
    }
}
