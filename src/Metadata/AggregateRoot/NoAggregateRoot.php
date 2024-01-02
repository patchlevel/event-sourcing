<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class NoAggregateRoot extends MetadataException
{
    /** @param class-string $aggregateRootClass */
    public function __construct(string $aggregateRootClass)
    {
        parent::__construct(sprintf('The class "%s" does not implement AggregateRoot', $aggregateRootClass));
    }
}
