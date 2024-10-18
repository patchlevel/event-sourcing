<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Source;

use Patchlevel\EventSourcing\Message\Message;

final class InMemorySource implements Source
{
    /** @param list<Message> $messages */
    public function __construct(
        private readonly array $messages
    ) {
    }

    /** @return iterable<Message> */
    public function load(): iterable
    {
        return $this->messages;
    }
}