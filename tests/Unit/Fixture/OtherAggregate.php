<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;

#[Aggregate('other')]
final class OtherAggregate extends BasicAggregateRoot
{
    #[Apply(ProfileCreated::class)]
    public function applyProfileCreated(): void
    {
    }
}
