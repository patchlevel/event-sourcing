<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Projection\BasicProjection;

final class LastEmailProjection extends BasicProjection
{
    /** @param list<string> $tags */
    public function __construct(
        private readonly array $tags = [],
    ) {
    }

    #[Apply]
    public function applyProfileCreated(string|null $state, ProfileCreated $event): string
    {
        return $event->email->toString();
    }

    public function initialState(): string|null
    {
        return null;
    }

    /** @return list<string> */
    public function tagFilter(): array
    {
        return $this->tags;
    }

    protected function lastEventIsEnough(): bool
    {
        return true;
    }
}
