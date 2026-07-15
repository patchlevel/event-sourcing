<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message;

use ArrayIterator;
use Generator;
use Iterator;
use IteratorIterator;
use Patchlevel\EventSourcing\Message\Translator\ChainTranslator;
use Patchlevel\EventSourcing\Message\Translator\Translator;
use Throwable;
use Traversable;

use function iterator_to_array;

/** @implements Iterator<int, Message> */
final class Stream implements Iterator
{
    /** @var Iterator<int, Message>|null */
    private Iterator|null $iterator;

    /** @var positive-int|0|null */
    private int|null $position = null;

    /** @param iterable<int, Message> $messages */
    public function __construct(iterable $messages = [])
    {
        if ($messages instanceof Iterator) {
            $this->iterator = $messages;
        } elseif ($messages instanceof Traversable) {
            $this->iterator = new IteratorIterator($messages);
        } else {
            $this->iterator = new ArrayIterator($messages);
        }
    }

    public function close(): void
    {
        $this->iterator = null;
        $this->position = null;
    }

    public function current(): Message|null
    {
        $this->assertNotClosed();

        return $this->iterator->valid() ? $this->iterator->current() : null;
    }

    public function key(): int|null
    {
        $this->assertNotClosed();

        return $this->iterator->valid() ? $this->iterator->key() : null;
    }

    public function next(): void
    {
        $this->initialize();
        $this->assertNotClosed();

        $this->iterator->next();

        if (!$this->iterator->valid()) {
            return;
        }

        $this->position = $this->position === null ? 0 : $this->position + 1;
    }

    public function rewind(): void
    {
        $this->assertNotClosed();

        try {
            $this->iterator->rewind();
        } catch (Throwable) {
            throw new StreamNotRewindable();
        }

        $this->position = null;
    }

    public function valid(): bool
    {
        $this->assertNotClosed();

        return $this->iterator->valid();
    }

    /** @phpstan-assert !null $this->iterator */
    private function assertNotClosed(): void
    {
        if ($this->iterator === null) {
            throw new StreamClosed();
        }
    }

    public function end(): bool
    {
        $this->assertNotClosed();

        return !$this->iterator->valid();
    }

    public function position(): int|null
    {
        $this->initialize();
        $this->assertNotClosed();

        return $this->position;
    }

    /**
     * Alias for key().
     */
    public function index(): int|null
    {
        return $this->key();
    }

    private function initialize(): void
    {
        if (!$this->iterator?->valid() || $this->position !== null) {
            return;
        }

        $this->position = 0;
    }

    /**
     * Converts the stream to a list and consumes it from the current position.
     *
     * @return list<Message>
     */
    public function toList(): array
    {
        $this->assertNotClosed();

        return iterator_to_array($this, false);
    }

    /**
     * Converts the stream to an indexed array and consumes it from the current position.
     *
     * @return array<int, Message>
     */
    public function toArray(): array
    {
        $this->assertNotClosed();

        return iterator_to_array($this);
    }

    /**
     * If the underlying iterator is single-pass (for example a Generator),
     * the transformed stream is single-pass as well.
     */
    public function transform(Translator ...$translators): Stream
    {
        $chainTranslator = new ChainTranslator($translators);

        $generator = function () use ($chainTranslator) {
            foreach ($this as $message) {
                foreach ($chainTranslator($message) as $translatedMessage) {
                    yield $translatedMessage;
                }
            }
        };

        return new Stream($generator());
    }

    /**
     * @param positive-int $size
     *
     * @return Generator<Stream>
     */
    public function chunk(int $size = 1000): Generator
    {
        $buffer = [];
        $bufferSize = 0;

        foreach ($this as $message) {
            $buffer[] = $message;
            $bufferSize++;

            if ($bufferSize !== $size) {
                continue;
            }

            yield new Stream($buffer);

            $buffer = [];
            $bufferSize = 0;
        }

        if ($bufferSize <= 0) {
            return;
        }

        yield new Stream($buffer);
    }
}
