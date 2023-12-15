<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRootId;

use function sprintf;

final class AggregateNotFound extends RepositoryException
{
    public function __construct(string $aggregateClass, AggregateRootId $id)
    {
        parent::__construct(sprintf('aggregate "%s::%s" not found', $aggregateClass, $id->toString()));
    }
}
