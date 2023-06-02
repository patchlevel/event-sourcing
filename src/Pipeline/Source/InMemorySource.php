<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Source;

use Patchlevel\EventSourcing\EventBus\Message;
use Traversable;

use function count;

final class InMemorySource implements Source
{
    /** @param iterable<Message> $messages */
    public function __construct(
        private readonly iterable $messages,
    ) {
    }

    /** @return Traversable<Message> */
    public function load(): Traversable
    {
        yield from $this->messages;
    }

    public function count(): int
    {
        return count($this->messages);
    }
}
