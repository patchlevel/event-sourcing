<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

use function sprintf;

final class AggregateDetached extends RepositoryException
{
    /** @param class-string<AggregateRoot> $aggregateClass */
    public function __construct(string $aggregateClass, string $aggregateId)
    {
        parent::__construct(
            sprintf(
                'Aggregate %s with the id "%s" was not loaded from this repository. you have to load the aggregate again.',
                $aggregateClass,
                $aggregateId,
            ),
        );
    }
}
