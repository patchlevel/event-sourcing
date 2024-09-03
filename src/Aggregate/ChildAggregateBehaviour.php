<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

/** @experimental  */
trait ChildAggregateBehaviour
{
    /** @var callable(object $event): void */
    private $recorder;

    protected function recordThat(object $event): void
    {
        ($this->recorder)($event);
    }

    /** @param callable(object $event): void $recorder */
    public function setRecorder(callable $recorder): void
    {
        $this->recorder = $recorder;
    }
}
