<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

interface HandlerProvider
{
    /**
     * @param class-string $commandClass
     *
     * @throws HandlerNotFound
     */
    public function handlerForCommand(string $commandClass): HandlerDescriptor;
}
