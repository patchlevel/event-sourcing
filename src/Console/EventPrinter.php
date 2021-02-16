<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Symfony\Component\Console\Style\SymfonyStyle;

use function get_class;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

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
                $date ? $date->format(DateTimeImmutable::ATOM) : 'null',
            ],
        ]);

        $console->block(json_encode($event->payload(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }
}
