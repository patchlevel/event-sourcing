<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use function sprintf;

final class WrongAggregate extends RepositoryException
{
    public function __construct(string $aggregateClass, string $expected)
    {
        parent::__construct(sprintf('aggregate "%s::%s" not found', $aggregateClass, $expected));
    }
}
