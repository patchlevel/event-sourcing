<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\EventBus\Message;
use Traversable;

/** @extends Traversable<Message> */
interface Stream extends Traversable
{
    public function close(): void;

    public function current(): Message|null;

    public function position(): int;
}
