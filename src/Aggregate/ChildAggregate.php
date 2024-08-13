<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

/** @experimental  */
interface ChildAggregate
{
    /** @param callable(object $event): void $recorder */
    public function setRecorder(callable $recorder): void;
}
