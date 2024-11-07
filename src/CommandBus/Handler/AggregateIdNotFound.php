<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus\Handler;

use RuntimeException;

use function sprintf;

final class AggregateIdNotFound extends RuntimeException
{
    /** @param class-string $commandClass */
    public function __construct(string $commandClass)
    {
        parent::__construct(sprintf('Missing `Id` Attribute in command %s', $commandClass));
    }
}
