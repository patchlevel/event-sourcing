<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Source;

use Generator;
use Patchlevel\EventSourcing\EventBus\Message;

use function count;

final class InMemorySource implements Source
{
    /** @var list<Message> */
    private array $messages;

    /**
     * @param list<Message> $messages
     */
    public function __construct(array $messages)
    {
        $this->messages = $messages;
    }

    /**
     * @return Generator<Message>
     */
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
