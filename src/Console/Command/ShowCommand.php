<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Serializer\Serializer;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function sprintf;

final class ShowCommand extends Command
{
    private Store $store;
    private Serializer $serializer;
    private AggregateRootRegistry $aggregateRootRegistry;

    public function __construct(Store $store, Serializer $serializer, AggregateRootRegistry $aggregateRootRegistry)
    {
        parent::__construct();

        $this->store = $store;
        $this->serializer = $serializer;
        $this->aggregateRootRegistry = $aggregateRootRegistry;
    }

    protected function configure(): void
    {
        $this
            ->setName('event-sourcing:show')
            ->setDescription('show events from one aggregate')
            ->addArgument('aggregate', InputArgument::REQUIRED, 'aggregate name')
            ->addArgument('id', InputArgument::REQUIRED, 'aggregate id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new OutputStyle($input, $output);

        $aggregate = InputHelper::string($input->getArgument('aggregate'));
        $id = InputHelper::string($input->getArgument('id'));

        if (!$this->aggregateRootRegistry->hasAggregateName($aggregate)) {
            $console->error(sprintf('aggregate type "%s" not exists', $aggregate));

            return 1;
        }

        $messages = $this->store->load($this->aggregateRootRegistry->aggregateClass($aggregate), $id);

        if (count($messages) === 0) {
            $console->error(sprintf('aggregate "%s" => "%s" not found', $aggregate, $id));

            return 1;
        }

        foreach ($messages as $message) {
            $console->message($this->serializer, $message);
        }

        return 0;
    }
}
