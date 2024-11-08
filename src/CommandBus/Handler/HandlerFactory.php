<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus\Handler;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

interface HandlerFactory
{
    /**
     * @param class-string<AggregateRoot> $aggregateClass
     *
     * @return callable(object): void
     */
    public function createHandler(string $aggregateClass, string $method): callable;

    /**
     * @param class-string<AggregateRoot> $aggregateClass
     *
     * @return callable(object): void
     */
    public function updateHandler(string $aggregateClass, string $method): callable;
}
