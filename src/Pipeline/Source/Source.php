<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Source;

use Generator;
use Patchlevel\EventSourcing\EventBus\Message;

interface Source
{
    /**
     * @return Generator<Message>
     */
    public function load(): Generator;

    public function count(): int;
}
