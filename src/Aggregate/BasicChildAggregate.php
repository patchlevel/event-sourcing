<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

abstract class BasicChildAggregate implements ChildAggregate, ChildAggregateMetadataAware
{
    use ChildAggregateAttributeBehaviour;
}
