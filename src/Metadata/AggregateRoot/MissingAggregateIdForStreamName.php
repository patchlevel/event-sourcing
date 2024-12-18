<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class MissingAggregateIdForStreamName extends MetadataException
{
    public function __construct(string $streamName)
    {
        parent::__construct(sprintf('Missing aggregate id for stream name "%s"', $streamName));
    }
}
