<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

interface CommandBus
{
    public function dispatch(object $command): void;
}
