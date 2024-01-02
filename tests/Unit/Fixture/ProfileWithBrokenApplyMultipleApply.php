<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Attribute\Apply;

#[Aggregate(ProfileWithBrokenApplyMultipleApply::class)]
final class ProfileWithBrokenApplyMultipleApply extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;

    #[Apply]
    #[Apply]
    protected function applyNameChanged(NameChanged $event): void
    {
    }
}
