<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber\EventEmitter;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Store;

final class StoreEventEmitter implements EventEmitter
{
    public function __construct(
        private readonly Store $store,
        private readonly string $defaultStream,
    ) {
    }

    /** @param list<object> $events */
    public function emit(array $events): void
    {
        $this->linkTo($this->defaultStream, $events);
    }

    /** @param list<object> $events */
    public function linkTo(string $streamName, array $events): void
    {
        if ($events === []) {
            return;
        }

        $messages = [];

        foreach ($events as $event) {
            $messages[] = Message::create($event)
                ->withHeader(new StreamNameHeader($streamName));
        }

        $this->store->save(...$messages);
    }
}
