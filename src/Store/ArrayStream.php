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

    /** @var positive-int|0|null */
    private int|null $position;

    /** @param list<Message> $messages The index is based on position. An offset is not supported. */
    public function __construct(array $messages = [])
    {
        $this->iterator = $messages === [] ? new ArrayIterator() : $this->createGenerator($messages);
        $this->position = null;
    }

    public function close(): void
    {
    }

    /** @return Traversable<Message> */
    public function getIterator(): Traversable
    {
        return $this->iterator;
    }

    /** @return positive-int|0|null */
    public function position(): int|null
    {
        if (!$this->position) {
            $this->iterator->key();
        }

        return $this->position;
    }

    /**
     * The index is based on position. An offset is not supported.
     *
     * @return positive-int|null
     */
    public function index(): int|null
    {
        $position = $this->position();

        if ($position === null) {
            return null;
        }

        return $position + 1;
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
            if ($this->position === null) {
                $this->position = 0;
            } else {
                $this->position++;
            }

            yield $message;
        }
    }
}
