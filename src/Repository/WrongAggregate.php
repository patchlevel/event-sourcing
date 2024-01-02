<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use function sprintf;

final class WrongAggregate extends RepositoryException
{
    /**
     * @param class-string $aggregateRootClass
     * @param class-string $expected
     */
    public function __construct(string $aggregateRootClass, string $expected)
    {
        parent::__construct(
            sprintf('Wrong aggregate given: got "%s" but expected "%s"', $aggregateRootClass, $expected),
        );
    }
}
