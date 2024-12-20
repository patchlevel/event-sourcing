<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use RuntimeException;

use function sprintf;

final class MultipleHandlersFound extends RuntimeException
{
    /** @param class-string $commandClass */
    public function __construct(string $commandClass)
    {
        parent::__construct(sprintf('Multiple handlers found for command "%s"', $commandClass));
    }
}
