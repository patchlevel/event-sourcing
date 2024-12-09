<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use RuntimeException;

use function sprintf;

final class MissingHandledBy extends RuntimeException
{
    public function __construct(string $commandClass)
    {
        parent::__construct(sprintf('Missing #[HandledBy] attribute for command "%s"', $commandClass));
    }
}
