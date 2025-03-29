<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Generator;
use IteratorAggregate;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Stream;
use Patchlevel\EventSourcing\Store\StreamClosed;

/**
 * @implements IteratorAggregate<Message>
 * @interal
 */
final class GeneratorStream implements Stream, IteratorAggregate
{
    /** @var Generator<Message>|null */
    private Generator|null $iterator;

    private Message|null $current = null;

    /** @var positive-int|null */
    private int|null $index = null;

    /** @var positive-int|0|null */
    private int|null $position = null;

    /** @param Generator<int, Message> $iterator */
    public function __construct(Generator $iterator)
    {
        $this->iterator = $this->generator($iterator);
    }

    public function close(): void
    {
        $this->iterator = null;
    }

    /** @return Generator<Message> */
    public function getIterator(): Generator
    {
        $this->assertNotClosed();

        return $this->iterator;
    }

    /** @return positive-int|0|null */
    public function position(): int|null
    {
        $this->assertNotClosed();

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

        return $this->current;
    }

    /**
     * @param Generator<int, Message> $messages
     *
     * @return Generator<Message>
     */
    private function generator(Generator $messages): Generator
    {
        foreach ($messages as $index => $message) {
            if ($this->position === null) {
                $this->position = 0;
            }

            $this->index = $index;
            $this->position++;
            $this->current = $message;

            yield $message;
        }
    }

    /** @phpstan-assert !null $this->iterator */
    private function assertNotClosed(): void
    {
        if ($this->iterator === null) {
            throw new StreamClosed();
        }
    }
}
