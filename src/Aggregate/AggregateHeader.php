<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use DateTimeImmutable;
use Patchlevel\EventSourcing\EventBus\Header;

/**
 * @psalm-immutable
 */
final class AggregateHeader implements Header
{
    /** @param positive-int $playhead */
    public function __construct(
        public readonly string $aggregateName,
        public readonly string $aggregateId,
        public readonly int $playhead,
        public readonly DateTimeImmutable $recordedOn,
    ) {
    }
}
