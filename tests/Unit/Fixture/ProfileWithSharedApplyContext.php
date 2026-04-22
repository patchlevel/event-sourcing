<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Attribute\SharedApplyContext;

#[Aggregate('profile_shared_context')]
#[SharedApplyContext([OtherAggregate::class])]
final class ProfileWithSharedApplyContext extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;
}
