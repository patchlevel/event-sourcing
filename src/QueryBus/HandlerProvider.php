<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\QueryBus;

interface HandlerProvider
{
    /**
     * @param class-string $queryClass
     *
     * @return iterable<int, HandlerDescriptor>
     */
    public function handlerForQuery(string $queryClass): iterable;
}
