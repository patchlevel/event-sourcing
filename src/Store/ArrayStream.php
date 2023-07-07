<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use ArrayIterator;
use Generator;
use Iterator;
use IteratorAggregate;
use Patchlevel\EventSourcing\EventBus\Message;
use Traversable;

/** @implements IteratorAggregate<Message> */
final class ArrayStream implements Stream, IteratorAggregate
{
    /** @var Iterator<Message> $iterator */
    private readonly Iterator $iterator;
    private int $position;

    /** @param list<Message> $messages */
    public function __construct(array $messages = [])
    {
        $this->iterator = $messages === [] ? new ArrayIterator() : $this->createGenerator($messages);
        $this->position = 0;
    }

    public function close(): void
    {
    }

    /** @return Traversable<Message> */
    public function getIterator(): Traversable
    {
        yield from $this->iterator;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function current(): Message|null
    {
        return $this->iterator->current() ?: null;
    }

    /**
     * @param list<Message> $messages
     *
     * @return Generator<Message>
     */
    private function createGenerator(array $messages): Generator
    {
        foreach ($messages as $message) {
            $this->position++;

            yield $message;
        }
    }
}
