<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\AggregateId;
use Patchlevel\EventSourcing\Attribute\Apply;

#[Aggregate(ProfileWithEmptyApply::class)]
final class ProfileWithEmptyApply extends BasicAggregateRoot
{
    #[AggregateId]
    private ProfileId $id;

    #[Apply]
    protected function applyProfileCreated(ProfileCreated|ProfileVisited $event): void
    {
    }

    #[Apply]
    protected function applyNameChanged(NameChanged $event): void
    {
    }
}
