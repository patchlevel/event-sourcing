<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Countable;
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;
use Stringable;

#[Aggregate('profile_with_broken_apply_union_intersection')]
final class ProfileWithBrokenApplyUnionIntersection extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;

    #[Apply]
    protected function applyEvent(ProfileCreated|(Countable&Stringable) $event): void
    {
    }
}
