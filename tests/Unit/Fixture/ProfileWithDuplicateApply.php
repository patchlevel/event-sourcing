<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;

#[Aggregate('profile_with_duplicate_apply')]
final class ProfileWithDuplicateApply extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;

    #[Apply]
    protected function applyA(ProfileCreated $event): void
    {
    }

    #[Apply]
    protected function applyB(ProfileCreated $event): void
    {
    }
}
