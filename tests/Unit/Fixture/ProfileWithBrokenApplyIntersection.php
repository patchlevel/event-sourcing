<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;

#[Aggregate(ProfileWithBrokenApplyIntersection::class)]
final class ProfileWithBrokenApplyIntersection extends BasicAggregateRoot
{
    public function aggregateRootId(): string
    {
        return self::class;
    }
}
