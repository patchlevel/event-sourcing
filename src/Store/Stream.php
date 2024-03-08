<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\EventBus\Message;
use Traversable;

/** @extends Traversable<Message> */
interface Stream extends Traversable
{
    public function close(): void;

    /** @throws StreamClosed */
    public function next(): void;

    /** @throws StreamClosed */
    public function current(): Message|null;

    /** @throws StreamClosed */
    public function end(): bool;

    /**
     * @return positive-int|0|null
     *
     * @throws StreamClosed
     */
    public function position(): int|null;

    /**
     * @return positive-int|null
     *
     * @throws StreamClosed
     */
    public function index(): int|null;
}
