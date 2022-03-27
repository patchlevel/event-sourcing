<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use DateTimeImmutable;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Serializer\Serializer;
use Symfony\Component\Console\Style\SymfonyStyle;

final class EventPrinter
{
    public function __construct(private Serializer $serializer)
    {
    }

    public function write(SymfonyStyle $console, Message $message): void
    {
        $event = $message->event();

        $console->title($event::class);

        $console->horizontalTable([
            'aggregateClass',
            'aggregateId',
            'playhead',
            'recordedOn',
        ], [
            [
                $message->aggregateClass(),
                $message->aggregateId(),
                $message->playhead(),
                $message->recordedOn()->format(DateTimeImmutable::ATOM),
            ],
        ]);

        $console->block($this->serializer->serialize($event));
    }
}
