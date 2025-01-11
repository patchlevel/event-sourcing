<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

final class ChainHandlerProvider implements HandlerProvider
{
    /** @param iterable<HandlerProvider> $providers */
    public function __construct(
        private readonly iterable $providers,
    ) {
    }

    /**
     * @param class-string $commandClass
     *
     * @return iterable<int, HandlerDescriptor>
     */
    public function handlerForCommand(string $commandClass): iterable
    {
        $handlers = [];

        foreach ($this->providers as $provider) {
            $handlers = [
                ...$handlers,
                ...$provider->handlerForCommand($commandClass),
            ];
        }

        return $handlers;
    }
}
