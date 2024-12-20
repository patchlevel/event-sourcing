<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

interface HandlerProvider
{
    /**
     * @param class-string $commandClass
     *
     * @return iterable<HandlerDescriptor>
     */
    public function handlerForCommand(string $commandClass): iterable;
}
