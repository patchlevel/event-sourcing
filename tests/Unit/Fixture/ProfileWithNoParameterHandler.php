<?php

// phpcs:ignoreFile

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Handle;

#[Aggregate(ProfileWithNoParameterHandler::class)]
final class ProfileWithNoParameterHandler extends BasicAggregateRoot
{
    #[Handle]
    public function noParameters(): void
    {
    }
}
