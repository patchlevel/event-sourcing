<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use function sprintf;

final class AggregateNotFound extends RepositoryException
{
    public function __construct(string $aggregateClass, string $id)
    {
        parent::__construct(sprintf('aggregate "%s::%s" not found', $aggregateClass, $id));
    }
}
