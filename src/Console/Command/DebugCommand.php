<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Command;

use Patchlevel\EventSourcing\Console\OutputStyle;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\EventRegistry;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_intersect_key;
use function array_keys;
use function array_map;
use function array_shift;
use function array_values;

#[AsCommand(
    'event-sourcing:debug',
    'Show event sourcing information (aggregates, events, subscribers)',
    ['debug:event-sourcing'],
)]
final class DebugCommand extends Command
{
    public function __construct(
        private readonly AggregateRootRegistry $aggregateRootRegistry,
        private readonly EventRegistry $eventRegistry,
        private readonly SubscriberAccessorRepository|null $subscriberAccessorRepository = null,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $console = new OutputStyle($input, $output);

        $this->showAggregates($console);
        $this->showEvents($console);
        $this->showSubscribers($console);

        return Command::SUCCESS;
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

    private function showSubscribers(OutputStyle $console): void
    {
        $eventNames = $this->eventRegistry->eventNames();

        $subscribers = [];

        /** @var MetadataSubscriberAccessor $subscriberAccessor */
        foreach ($this->subscriberAccessorRepository?->all() ?? [] as $subscriberAccessor) {
            $metadata = $subscriberAccessor->metadata();

            $eventsHandled = array_intersect_key($eventNames, $metadata->subscribeMethods);

            $subscribers[] = [
                $metadata->id,
                $metadata->group,
                $metadata->runMode->value,
                // first event name handled
                array_shift($eventsHandled),
            ];

            // display more event names that the subscriber can handle (if any)
            foreach ($eventsHandled as $eventName) {
                $subscribers[] = ['', '', '', $eventName];
            }
        }

        $console->title('Subscribers');

        $console->table(
            [
                'id',
                'group',
                'run mode',
                'events handled',
            ],
            $subscribers,
        );
    }
}
