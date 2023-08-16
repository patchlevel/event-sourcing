<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use DateTimeInterface;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Symfony\Component\Console\Style\SymfonyStyle;

final class OutputStyle extends SymfonyStyle
{
    public function message(EventSerializer $serializer, Message $message): void
    {
        $event = $message->event();
        $data = $serializer->serialize($event, [Encoder::OPTION_PRETTY_PRINT => true]);

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
                $message->recordedOn()->format(DateTimeInterface::ATOM),
            ],
        ]);

        $this->block($data->payload);
    }
}
