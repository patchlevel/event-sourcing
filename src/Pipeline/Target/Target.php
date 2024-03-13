<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\Message\Message;

interface Target
{
    public function save(Message ...$messages): void;
}
