<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\EventBus\Message;
use Traversable;

/**
 * @implements Traversable<Message>
 */
interface Stream extends Traversable
{
    public function close(): void;

    public function current(): ?Message;

    public function position(): int;
}
