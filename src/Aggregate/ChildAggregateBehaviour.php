<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\Hydrator\Attribute\Ignore;

/** @experimental  */
trait ChildAggregateBehaviour
{
    /** @var callable(object $event): void */
    #[Ignore]
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
