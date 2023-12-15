<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\AggregateId;
use Patchlevel\EventSourcing\Attribute\Apply;

#[Aggregate(ProfileWithBrokenApplyBothUsage::class)]
final class ProfileWithBrokenApplyBothUsage extends BasicAggregateRoot
{
    #[AggregateId]
    private ProfileId $id;

    #[Apply(ProfileCreated::class)]
    #[Apply]
    protected function applyProfileCreated(ProfileCreated|ProfileVisited $event): void
    {
    }
}
