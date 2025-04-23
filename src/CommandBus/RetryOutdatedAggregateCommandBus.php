<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use Patchlevel\EventSourcing\Attribute\RetryAggregateOutdated;
use Patchlevel\EventSourcing\Repository\AggregateOutdated;
use ReflectionClass;

final class RetryOutdatedAggregateCommandBus implements CommandBus
{
    public function __construct(
        private readonly CommandBus $commandBus,
    ) {
    }

    public function dispatch(object $command): void
    {
        $this->doDispatch($command, 0);
    }

    private function doDispatch(object $command, int $retry, int|null $maxRetries = null): void
    {
        try {
            $this->commandBus->dispatch($command);
        } catch (AggregateOutdated $exception) {
            $maxRetries ??= $this->maxRetries($command);

            if ($retry >= $maxRetries) {
                throw $exception;
            }

            $this->doDispatch($command, $retry + 1, $maxRetries);
        }
    }

    private function maxRetries(object $command): int|null
    {
        $reflectionClass = new ReflectionClass($command);
        $attributes = $reflectionClass->getAttributes(RetryAggregateOutdated::class);

        if ($attributes === []) {
            return 0;
        }

        return $attributes[0]->newInstance()->maxRetries;
    }
}
