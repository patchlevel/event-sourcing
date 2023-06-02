<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_keys;
use function array_map;
use function array_values;

#[AsCommand(
    'event-sourcing:debug',
    'show event sourcing debug information',
    ['debug:event-sourcing'],
)]
final class DebugCommand extends Command
{
    public function __construct(
        private readonly AggregateRootRegistry $aggregateRootRegistry,
        private readonly EventRegistry $eventRegistry,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new OutputStyle($input, $output);

        $this->showAggregates($console);
        $this->showEvents($console);

        return 0;
    }

    private function showAggregates(OutputStyle $console): void
    {
        $console->title('Aggregates');

        $aggregates = $this->aggregateRootRegistry->aggregateClasses();

        $console->table(
            ['name', 'class'],
            array_map(null, array_keys($aggregates), array_values($aggregates)),
        );
    }

    private function showEvents(OutputStyle $console): void
    {
        $console->title('Events');

        $events = $this->eventRegistry->eventClasses();

        $console->table(
            ['name', 'class'],
            array_map(null, array_keys($events), array_values($events)),
        );
    }
}
