<?php

namespace Patchlevel\EventSourcing\Repository\StoreAdapter;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Stream;

interface StoreAdapter
{
    public function load(string $stream, int|null $fromPlayhead = null): Stream;

    public function count(string $stream): int;

    /**
     * @param iterable<Message> $messages
     */
    public function write(string $stream, iterable $messages): void;
}