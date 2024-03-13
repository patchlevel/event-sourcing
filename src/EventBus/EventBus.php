<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Patchlevel\EventSourcing\Message\Message;

interface EventBus
{
    public function dispatch(Message ...$messages): void;
}
