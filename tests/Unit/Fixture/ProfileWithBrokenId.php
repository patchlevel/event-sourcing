<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Id;

#[Aggregate(ProfileWithBrokenId::class)]
final class ProfileWithBrokenId extends BasicAggregateRoot
{
    #[Id]
    private int $id = 0;

    public static function create(): self
    {
        return new self();
    }
}
