<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\Hydrator\Attribute\Ignore;
use Patchlevel\Hydrator\Attribute\NormalizedName;

trait ChildAggregateBehaviour
{
    #[NormalizedName('_playhead')]
    private int $playhead = 0;

    /**
     * @var callable(object $event): void
     */
    private $recorder;

    /**
     * @param callable(object $event): void $recorder
     */
    final protected function __construct(callable $recorder)
    {
        $this->recorder = $recorder;
    }

    abstract public function apply(object $event): void;

    protected function recordThat(object $event): void
    {
        ($this->recorder)($event);
        $this->playhead++;
        $this->apply($event);
    }

    /** @param iterable<object> $events */
    public function catchUp(iterable $events): void
    {
        foreach ($events as $event) {
            $this->playhead++;
            $this->apply($event);
        }
    }

    public function playhead(): int
    {
        return $this->playhead;
    }
}
