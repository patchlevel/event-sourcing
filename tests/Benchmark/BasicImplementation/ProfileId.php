<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation;

use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Aggregate\RamseyAggregateIdBehaviour;

final class ProfileId implements AggregateRootId
{
    use RamseyAggregateIdBehaviour;
}
