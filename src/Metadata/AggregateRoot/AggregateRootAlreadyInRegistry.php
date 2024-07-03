<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class AggregateRootAlreadyInRegistry extends MetadataException
{
    public function __construct(string $aggregateName)
    {
        parent::__construct(sprintf(
            'The aggregate name "%s" is already used in the registry. Maybe you defined 2 aggregates with the same name.',
            $aggregateName,
        ));
    }
}
