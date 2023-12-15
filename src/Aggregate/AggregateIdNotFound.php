<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use RuntimeException;

use function sprintf;

final class AggregateIdNotFound extends RuntimeException
{
    /** @param class-string $className */
    public function __construct(string $className)
    {
        parent::__construct(sprintf('class %s has no property marked as aggregate id', $className));
    }
}
