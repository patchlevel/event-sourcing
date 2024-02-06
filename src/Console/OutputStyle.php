<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use DateTimeInterface;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Serializer\Encoder\Encoder;
use Patchlevel\EventSourcing\Serializer\EventSerializer;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function sprintf;

final class OutputStyle extends SymfonyStyle
{
    public function message(EventSerializer $serializer, Message $message): void
    {
        $event = $message->event();
        $data = $serializer->serialize($event, [Encoder::OPTION_PRETTY_PRINT => true]);

        $this->title($data->name);

        $this->horizontalTable([
            'eventClass',
            'aggregateName',
            'aggregateId',
            'playhead',
            'recordedOn',
        ], [
            [
                $event::class,
                $message->aggregateName(),
                $message->aggregateId(),
                $message->playhead(),
                $message->recordedOn()->format(DateTimeInterface::ATOM),
            ],
        ]);

        $this->block($data->payload);
    }

    public function throwable(Throwable $error): void
    {
        $number = 1;

        do {
            $this->error(sprintf('%d) %s', $number, $error->getMessage()));
            $this->block($error->getTraceAsString());

            $number++;
            $error = $error->getPrevious();
        } while ($error !== null);
    }
}
