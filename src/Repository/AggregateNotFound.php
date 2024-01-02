<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRootId;

use function sprintf;

final class AggregateNotFound extends RepositoryException
{
    public function __construct(string $aggregateRootClass, AggregateRootId $rootId)
    {
        parent::__construct(sprintf('aggregate "%s::%s" not found', $aggregateRootClass, $rootId->toString()));
    }
}
