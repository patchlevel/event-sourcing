<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Source;

use Patchlevel\EventSourcing\EventBus\Message;
use Traversable;

interface Source
{
    /** @return Traversable<Message> */
    public function load(): Traversable;

    public function count(): int;
}
