<?php

namespace Patchlevel\EventSourcing\Tool\Console;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Symfony\Component\Console\Style\SymfonyStyle;

class EventPrinter
{
    public function write(SymfonyStyle $console, AggregateChanged $event): void
    {
        $console->title(get_class($event));

        $date = $event->recordedOn();

        $console->horizontalTable([
            'aggregateId',
            'playhead',
            'recordedOn',
        ], [
            [
                $event->aggregateId(),
                $event->playhead(),
                $date ? $date->format(\DateTimeImmutable::ATOM) : 'null',
            ]
        ]);

        $console->block(json_encode($event->payload(), JSON_PRETTY_PRINT));
    }
}
