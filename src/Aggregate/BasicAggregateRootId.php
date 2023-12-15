<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

final class BasicAggregateRootId implements AggregateRootId
{
    use ValueAggregateIdBehaviour;
}
