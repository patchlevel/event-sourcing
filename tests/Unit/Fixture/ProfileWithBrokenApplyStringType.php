<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;

#[Aggregate('profile_with_broken_apply_string_type')]
final class ProfileWithBrokenApplyStringType extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;

    #[Apply]
    protected function applyEvent(string $event): void
    {
    }
}
