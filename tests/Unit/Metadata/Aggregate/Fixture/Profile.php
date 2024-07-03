<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
}
