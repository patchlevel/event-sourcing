<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Id;

final class ProfileWithoutAggregateAttribute extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;
}
