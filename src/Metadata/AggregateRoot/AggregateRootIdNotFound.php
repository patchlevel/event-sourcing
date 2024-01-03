<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use RuntimeException;

use function sprintf;

final class AggregateRootIdNotFound extends RuntimeException
{
    public function __construct(string $aggregateRootClass)
    {
        parent::__construct(sprintf('class %s has no property marked as aggregate root id', $aggregateRootClass));
    }
}
