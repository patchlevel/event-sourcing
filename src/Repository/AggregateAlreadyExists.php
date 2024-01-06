<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;

use function sprintf;

final class AggregateAlreadyExists extends RepositoryException
{
    /** @param class-string<AggregateRoot> $aggregate */
    public function __construct(string $aggregate, AggregateRootId $id)
    {
        parent::__construct(
            sprintf(
                'aggregate %s with id %s already exists',
                $aggregate,
                $id->toString(),
            ),
        );
    }
}
