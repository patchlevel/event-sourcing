<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Source;

use Generator;
use Patchlevel\EventSourcing\EventBus\Message;

use function count;

final class InMemorySource implements Source
{
    /** @param list<Message> $messages */
    public function __construct(private array $messages)
    {
    }

    /** @return Generator<Message> */
    public function load(): Generator
    {
        foreach ($this->messages as $event) {
            yield $event;
        }
    }

    public function count(): int
    {
        return count($this->messages);
    }
}
