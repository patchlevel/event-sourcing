<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;

use function sprintf;

final class AggregateUnknown extends RepositoryException
{
    /** @param class-string<AggregateRoot> $aggregateRootClass */
    public function __construct(string $aggregateRootClass, AggregateRootId $aggregateRootId)
    {
        parent::__construct(
            sprintf(
                'The aggregate %s with the ID "%s" was not loaded from this repository. Please reload the aggregate.',
                $aggregateRootClass,
                $aggregateRootId->toString(),
            ),
        );
    }
}
