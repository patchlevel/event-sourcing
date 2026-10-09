<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Attribute\Stream;

#[Aggregate('profile_with_aggregate_stream')]
#[Stream(Profile::class)]
final class ProfileWithAggregateStream extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;
}
