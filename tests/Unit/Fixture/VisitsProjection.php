<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Checkpoint;
use Patchlevel\EventSourcing\Projection\BasicProjection;

final class VisitsProjection extends BasicProjection
{
    /** @param list<string> $tags */
    public function __construct(
        private readonly array $tags,
    ) {
    }

    public function initialState(): int
    {
        return 0;
    }

    #[Apply]
    public function applyProfileVisited(int $state, ProfileVisited $event): int
    {
        return $state + 1;
    }

    #[Apply]
    #[Checkpoint]
    public function applySplittingEvent(int $state, SplittingEvent $event): int
    {
        return $event->visits;
    }

    /** @return list<string> */
    protected function tagFilter(): array
    {
        return $this->tags;
    }
}
