<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Serializer\Serializer;
use Patchlevel\EventSourcing\Store\Store;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_flip;
use function array_key_exists;
use function count;
use function sprintf;

final class ShowCommand extends Command
{
    private Store $store;

    private Serializer $serializer;
    /** @var array<class-string<AggregateRoot>, string> */
    private array $aggregates;

    /**
     * @param array<class-string<AggregateRoot>, string> $aggregates
     */
    public function __construct(Store $store, Serializer $serializer, array $aggregates)
    {
        parent::__construct();

        $this->store = $store;
        $this->serializer = $serializer;
        $this->aggregates = $aggregates;
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

        $map = array_flip($this->aggregates);

        $aggregate = InputHelper::string($input->getArgument('aggregate'));
        $id = InputHelper::string($input->getArgument('id'));

        if (!array_key_exists($aggregate, $map)) {
            $console->error(sprintf('aggregate type "%s" not exists', $aggregate));

            return 1;
        }

        $messages = $this->store->load($map[$aggregate], $id);

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
