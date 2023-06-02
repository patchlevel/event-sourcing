<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

final class CriteriaBuilder
{
    private string|null $aggregateClass = null;
    private string|null $aggregateId = null;
    private int|null $limit = null;
    private int|null $fromIndex = null;
    private int|null $fromPlayhead = null;
    private bool|null $archived = null;

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

    public function limit(int|null $limit): self
    {
        $this->limit = $limit;

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
            $this->limit,
            $this->fromIndex,
            $this->fromPlayhead,
            $this->archived,
        );
    }
}
