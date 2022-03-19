<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use DateTimeImmutable;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Serializer\Hydrator;
use Symfony\Component\Console\Style\SymfonyStyle;

use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

final class EventPrinter
{
    private Hydrator $hydrator;

    public function __construct()
    {
        $this->hydrator = new Hydrator();
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

        $payload = $this->hydrator->extract($event);

        $console->block(json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }
}
