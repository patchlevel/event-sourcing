<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

abstract class BasicAggregateRoot implements AggregateRoot, AggregateRootMetadataAware
{
    use AggregateRootAttributeBehaviour;
}
