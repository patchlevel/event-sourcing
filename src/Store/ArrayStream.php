<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use ArrayIterator;
use Generator;
use Iterator;
use IteratorAggregate;
use Patchlevel\EventSourcing\EventBus\Message;
use Traversable;

final class ArrayStream implements Stream, IteratorAggregate
{
    /** @param Iterator<Message> $iterator */
    private readonly Iterator $iterator;
    private int $position;

    /**
     * @param list<Message> $messages
     */
    public function __construct(array $messages = [])
    {
        $this->iterator = $messages === [] ? new ArrayIterator() : $this->createTraversable($messages);
        $this->position = 0;
    }

    public function close(): void
    {

    }

    public function getIterator(): Traversable
    {
        yield from $this->iterator;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function current(): ?Message
    {
        return $this->iterator->current() ?: null;
    }

    /**
     * @param iterable<Message> $messages
     *
     * @return Generator<Message>
     */
    private function createTraversable(array $messages): Generator
    {
        foreach ($messages as $message) {
            $this->position++;

            yield $message;
        }
    }
}
