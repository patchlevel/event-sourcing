<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;

#[Aggregate(ProfileWithBrokenApplyBothUsage::class)]
final class ProfileWithBrokenApplyBothUsage extends AggregateRoot
{
    #[Apply(ProfileCreated::class)]
    #[Apply]
    protected function applyProfileCreated(ProfileCreated|ProfileVisited $event): void
    {
    }

    public function aggregateRootId(): string
    {
        return self::class;
    }
}
