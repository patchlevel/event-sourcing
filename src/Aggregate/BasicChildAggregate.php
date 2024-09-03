<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

/** @experimental  */
abstract class BasicChildAggregate implements ChildAggregate
{
    use ChildAggregateBehaviour;
}
