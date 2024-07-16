<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

trait AggregateRootWithChildsBehaviour
{
    /**
     * @return array<ChildAggregate>
     */
    abstract public function getChildren(): array;
    protected function applyWithChildren(object $event): void
    {
        $this->rootApply($event);

        foreach ($this->getChildren() as $child) {
            $child->setRecorder($this->recordThat(...));
            $child->apply($event);
        }
    }
}
