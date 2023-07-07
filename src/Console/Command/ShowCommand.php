<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Patchlevel\EventSourcing\Store\Criteria;
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
    'event-sourcing:show',
    'show events from one aggregate',
)]
final class ShowCommand extends Command
{
    public function __construct(
        private Store $store,
        private EventSerializer $serializer,
        private AggregateRootRegistry $aggregateRootRegistry,
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
        if ($id === null) {
            $question = new Question('Enter the aggregate id');
            $id = InputHelper::string($console->askQuestion($question));
        }

        if (!$this->aggregateRootRegistry->hasAggregateName($aggregate)) {
            $console->error(sprintf('aggregate type "%s" not exists', $aggregate));

            return 1;
        }

        $stream = $this->store->load(
            new Criteria($this->aggregateRootRegistry->aggregateClass($aggregate), $id),
        );

        $hasMessage = false;
        foreach ($stream as $message) {
            $hasMessage = true;
            $console->message($this->serializer, $message);
        }

        if ($hasMessage) {
            return 0;
        }

        $console->error(sprintf('aggregate "%s" => "%s" not found', $aggregate, $id));

        return 1;
    }
}
