<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

final class CriteriaBuilder
{
    /** @var class-string<AggregateRoot>|null */
    private string|null $aggregateClass = null;
    private string|null $aggregateId = null;
    private int|null $fromIndex = null;
    private int|null $fromPlayhead = null;
    private bool|null $archived = null;

    /** @param class-string<AggregateRoot>|null $aggregateClass */
    public function aggregateClass(string|null $aggregateClass): self
    {
        $this->aggregateClass = $aggregateClass;

        return $this;
    }

    public function aggregateId(string|null $aggregateId): self
    {
        $this->aggregateId = $aggregateId;

        return $this;
    }

    public function fromIndex(int|null $fromIndex): self
    {
        $this->fromIndex = $fromIndex;

        return $this;
    }

    public function fromPlayhead(int|null $fromPlayhead): self
    {
        $this->fromPlayhead = $fromPlayhead;

        return $this;
    }

    public function archived(bool|null $archived): self
    {
        $this->archived = $archived;

        return $this;
    }

    public function build(): Criteria
    {
        return new Criteria(
            $this->aggregateClass,
            $this->aggregateId,
            $this->fromIndex,
            $this->fromPlayhead,
            $this->archived,
        );
    }
}
