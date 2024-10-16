<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use RuntimeException;

use function sprintf;

final class InvalidAggregateStreamName extends RuntimeException
{
    public function __construct(string $stream)
    {
        parent::__construct(sprintf('Invalid aggregate stream name "%s". Expected format is "[aggregateName]-[aggregateId]".', $stream));
    }
}
