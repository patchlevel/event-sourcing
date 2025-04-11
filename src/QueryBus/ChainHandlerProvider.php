<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\QueryBus;

final class ChainHandlerProvider implements HandlerProvider
{
    /** @param iterable<HandlerProvider> $providers */
    public function __construct(
        private readonly iterable $providers,
    ) {
    }

    /**
     * @param class-string $queryClass
     *
     * @return iterable<int, HandlerDescriptor>
     */
    public function handlerForQuery(string $queryClass): iterable
    {
        $handlers = [];

        foreach ($this->providers as $provider) {
            $handlers = [
                ...$handlers,
                ...$provider->handlerForQuery($queryClass),
            ];
        }

        return $handlers;
    }
}
