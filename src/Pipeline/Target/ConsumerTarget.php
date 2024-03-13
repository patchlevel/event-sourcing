<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\EventBus\Consumer;
use Patchlevel\EventSourcing\EventBus\DefaultConsumer;
use Patchlevel\EventSourcing\Message\Message;

final class ConsumerTarget implements Target
{
    public function __construct(
        private readonly Consumer $consumer,
    ) {
    }

    public function save(Message ...$messages): void
    {
        foreach ($messages as $message) {
            $this->consumer->consume($message);
        }
    }

    /** @param iterable<object> $listeners */
    public static function create(iterable $listeners): self
    {
        return new self(DefaultConsumer::create($listeners));
    }
}
