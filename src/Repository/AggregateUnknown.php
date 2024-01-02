<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;

use function sprintf;

final class AggregateUnknown extends RepositoryException
{
    /** @param class-string<AggregateRoot> $aggregateClass */
    public function __construct(string $aggregateClass, AggregateRootId $aggregateId)
    {
        parent::__construct(
            sprintf(
                'The aggregate %s with the ID "%s" was not loaded from this repository. Please reload the aggregate.',
                $aggregateClass,
                $aggregateId->toString(),
            ),
        );
    }
}