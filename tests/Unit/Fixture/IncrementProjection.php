<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Projection\BasicProjection;

final class IncrementProjection extends BasicProjection
{
    /** @param list<string> $tags */
    public function __construct(
        private readonly int $initial,
        private readonly array $tags = [],
        private readonly string|null $streamName = null,
    ) {
    }

    #[Apply]
    public function applyProfileCreated(int $state, ProfileCreated $event): int
    {
        return $state + 1;
    }

    public function initialState(): int
    {
        return $this->initial;
    }

    /** @return list<string> */
    public function tagFilter(): array
    {
        return $this->tags;
    }

    public function streamName(): string|null
    {
        return $this->streamName;
    }
}
