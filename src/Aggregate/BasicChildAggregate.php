<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\Hydrator\Normalizer\ObjectNormalizer;

/** @experimental  */
#[ObjectNormalizer]
abstract class BasicChildAggregate implements ChildAggregate
{
    use ChildAggregateBehaviour;
}
