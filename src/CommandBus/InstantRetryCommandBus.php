<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use Patchlevel\EventSourcing\Attribute\InstantRetry;
use Patchlevel\EventSourcing\Repository\AggregateOutdated;
use Patchlevel\EventSourcing\Store\AppendConditionNotMet;
use ReflectionClass;
use Throwable;

use function in_array;

final class InstantRetryCommandBus implements CommandBus
{
    /**
     * @param positive-int                  $defaultMaxRetries
     * @param list<class-string<Throwable>> $defaultExceptions
     */
    public function __construct(
        private readonly CommandBus $commandBus,
        private readonly int $defaultMaxRetries = 3,
        private readonly array $defaultExceptions = [AggregateOutdated::class, AppendConditionNotMet::class],
    ) {
    }

    public function dispatch(object $command): void
    {
        $this->doDispatch($command, 0);
    }

    private function doDispatch(object $command, int $retry): void
    {
        try {
            $this->commandBus->dispatch($command);
        } catch (Throwable $exception) {
            $configuration = $this->configuration($command);

            if ($configuration === null) {
                throw $exception;
            }

            $exceptions = $configuration->exceptions ?? $this->defaultExceptions;
            $maxRetries = $configuration->maxRetries ?? $this->defaultMaxRetries;

            if ($retry >= $maxRetries || !in_array($exception::class, $exceptions, true)) {
                throw $exception;
            }

            $this->doDispatch($command, $retry + 1);
        }
    }

    private function configuration(object $command): InstantRetry|null
    {
        $reflectionClass = new ReflectionClass($command);
        $attributes = $reflectionClass->getAttributes(InstantRetry::class);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}
