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

    /** @var positive-int|null */
    private int|null $index;

    /** @param array<positive-int|0, Message> $messages The index is the key. An offset is not supported. */
    public function __construct(array $messages = [])
    {
        $this->iterator = $messages === [] ? new ArrayIterator() : $this->createGenerator($messages);
        $this->position = null;
        $this->index = null;
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
        if (!$this->index) {
            $this->iterator->key();
        }

        return $this->index;
    }

    public function next(): void
    {
        $this->iterator->next();
    }

    public function end(): bool
    {
        return !$this->iterator->valid();
    }

    public function current(): Message|null
    {
        return $this->iterator->current() ?: null;
    }

    /**
     * @param array<positive-int|0, Message> $messages
     *
     * @return Generator<Message>
     */
    private function createGenerator(array $messages): Generator
    {
        $hasIndex = true;

        foreach ($messages as $index => $message) {
            if ($this->position === null) {
                $this->position = 0;
            } else {
                $this->position++;
            }

            if ($index === 0) {
                $hasIndex = false;
            }

            if ($hasIndex) {
                $this->index = $index;
            } else {
                $this->index = $this->position + 1;
            }

            yield $message;
        }
    }
}
