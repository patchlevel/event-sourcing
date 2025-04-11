<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\QueryBus;

use InvalidArgumentException;

use function sprintf;

final class InvalidQueryHandler extends InvalidArgumentException
{
    public static function noHandler(string $queryClass): self
    {
        return new self(sprintf('No handler found for query %s', $queryClass));
    }

    public static function multipleHandler(string $queryClass): self
    {
        return new self(sprintf('Multiple handlers found for query %s', $queryClass));
    }
}
