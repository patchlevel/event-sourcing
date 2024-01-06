<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;

use function sprintf;

final class AggregateOutdated extends RepositoryException
{
    /** @param class-string<AggregateRoot> $aggregate */
    public function __construct(string $aggregate, AggregateRootId $id)
    {
        parent::__construct(
            sprintf(
                'Aggregate %s with id %s is outdated. There are new events in the store. Please reload the aggregate.',
                $aggregate,
                $id->toString(),
            ),
        );
    }
}
