<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use DateTimeImmutable;
use Patchlevel\Hydrator\Normalizer\DateTimeImmutableNormalizer;

/** @immutable */
final class AggregateHeader
{
    /** @param positive-int $playhead */
    public function __construct(
        public readonly string $aggregateName,
        public readonly string $aggregateId,
        public readonly int $playhead,
        #[DateTimeImmutableNormalizer]
        public readonly DateTimeImmutable $recordedOn,
    ) {
    }

    public function streamName(): string
    {
        return $this->aggregateName . '-' . $this->aggregateId;
    }
}
