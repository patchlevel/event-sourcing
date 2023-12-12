<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use function sprintf;

final class WrongAggregate extends RepositoryException
{
    /**
     * @param class-string $aggregateClass
     * @param class-string $expected
     */
    public function __construct(string $aggregateClass, string $expected)
    {
        parent::__construct(
            sprintf('Wrong aggregate given: got "%s" but expected "%s"', $aggregateClass, $expected),
        );
    }
}
