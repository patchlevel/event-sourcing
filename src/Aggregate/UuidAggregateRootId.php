<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

final class UuidAggregateRootId implements AggregateRootId
{
    use RamseyAggregateIdBehaviour;
}
