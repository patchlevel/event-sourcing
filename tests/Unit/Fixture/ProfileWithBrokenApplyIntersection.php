<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\AggregateId;
use Patchlevel\EventSourcing\Attribute\Apply;

#[Aggregate(ProfileWithBrokenApplyIntersection::class)]
final class ProfileWithBrokenApplyIntersection extends BasicAggregateRoot
{
    #[AggregateId]
    private ProfileId $id;

    #[Apply]
    protected function applyIntersection(ProfileCreated&ProfileVisited $event): void
    {
    }
}
