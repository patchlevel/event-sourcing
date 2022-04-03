<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use DateTimeImmutable;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Serializer\Serializer;
use Symfony\Component\Console\Style\SymfonyStyle;

class OutputStyle extends SymfonyStyle
{
    public function message(Serializer $serializer, Message $message): void
    {
        $event = $message->event();
        $data = $serializer->serialize($event, [Serializer::OPTION_PRETTY_PRINT => true]);

        $this->title($data->name);

        $this->horizontalTable([
            'eventClass',
            'aggregateClass',
            'aggregateId',
            'playhead',
            'recordedOn',
        ], [
            [
                $event::class,
                $message->aggregateClass(),
                $message->aggregateId(),
                $message->playhead(),
                $message->recordedOn()->format(DateTimeImmutable::ATOM),
            ],
        ]);

        $this->block($data->payload);
    }
}
