<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;

#[Aggregate(ProfileWithBrokenApplyIntersection::class)]
final class ProfileWithBrokenApplyIntersection extends AggregateRoot
{
    #[Apply]
    protected function applyIntersection(ProfileCreated&ProfileVisited $event): void
    {
    }

    public function aggregateRootId(): string
    {
        return self::class;
    }
}
