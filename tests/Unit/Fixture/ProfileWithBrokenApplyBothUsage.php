<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;

#[Aggregate(ProfileWithBrokenApplyBothUsage::class)]
final class ProfileWithBrokenApplyBothUsage extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;

    #[Apply(ProfileCreated::class)]
    #[Apply]
    protected function applyProfileCreated(ProfileCreated|ProfileVisited $event): void
    {
    }
}
