<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\EventBus\Message;
use Traversable;

/** @extends Traversable<Message> */
interface Stream extends Traversable
{
    public function close(): void;

    public function next(): void;

    public function current(): Message|null;

    public function end(): bool;

    /** @return positive-int|0|null */
    public function position(): int|null;

    /** @return positive-int|null */
    public function index(): int|null;
}
