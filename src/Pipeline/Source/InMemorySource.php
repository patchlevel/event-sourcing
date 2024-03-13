<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Source;

use Patchlevel\EventSourcing\Message\Message;

use function count;
use function is_array;
use function iterator_to_array;

final class InMemorySource implements Source
{
    /** @param iterable<Message> $messages */
    public function __construct(
        private readonly iterable $messages,
    ) {
    }

    /** @return iterable<Message> */
    public function load(): iterable
    {
        yield from $this->messages;
    }

    public function count(): int
    {
        if (is_array($this->messages)) {
            return count($this->messages);
        }

        return count(iterator_to_array($this->messages));
    }
}
