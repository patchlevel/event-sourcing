<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use ArrayIterator;
use Generator;
use Iterator;
use IteratorAggregate;
use Patchlevel\EventSourcing\Message\Message;
use Traversable;

/** @implements IteratorAggregate<Message> */
final class ArrayStream implements Stream, IteratorAggregate
{
    /** @var Iterator<Message>|null $iterator */
    private Iterator|null $iterator;

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
        $this->iterator = null;
    }

    /** @return Traversable<Message> */
    public function getIterator(): Traversable
    {
        $this->assertNotClosed();

        return $this->iterator;
    }

    /** @return positive-int|0|null */
    public function position(): int|null
    {
        $this->assertNotClosed();

        if ($this->position === null) {
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
        $this->assertNotClosed();

        if ($this->index === null) {
            $this->iterator->key();
        }

        return $this->index;
    }

    public function next(): void
    {
        $this->assertNotClosed();

        $this->iterator->next();
    }

    public function end(): bool
    {
        $this->assertNotClosed();

        return !$this->iterator->valid();
    }

    public function current(): Message|null
    {
        $this->assertNotClosed();

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

    /** @psalm-assert !null $this->iterator */
    private function assertNotClosed(): void
    {
        if ($this->iterator === null) {
            throw new StreamClosed();
        }
    }
}
