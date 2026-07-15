<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use Throwable;

interface CommandBus
{
    /**
     * @throws HandlerNotFound
     * @throws Throwable
     */
    public function dispatch(object $command): void;
}
