<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

interface HandlerProvider
{
    public function handlerForCommand(object $command): HandlerDescriptor;
}
