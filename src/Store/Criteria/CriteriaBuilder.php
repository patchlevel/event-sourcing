<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Criteria;

final class CriteriaBuilder
{
    private string|null $streamName = null;
    private string|null $aggregateName = null;
    private string|null $aggregateId = null;
    private int|null $fromIndex = null;
    private int|null $fromPlayhead = null;
    private bool|null $archived = null;

    /** @experimental */
    public function streamName(string|null $streamName): self
    {
        $this->streamName = $streamName;

        return $this;
    }

    public function aggregateName(string|null $aggregateName): self
    {
        $this->aggregateName = $aggregateName;

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
        $criteria = [];

        if ($this->streamName !== null) {
            $criteria[] = new StreamCriterion($this->streamName);
        }

        if ($this->aggregateName !== null) {
            $criteria[] = new AggregateNameCriterion($this->aggregateName);
        }

        if ($this->aggregateId !== null) {
            $criteria[] = new AggregateIdCriterion($this->aggregateId);
        }

        if ($this->fromPlayhead !== null) {
            $criteria[] = new FromPlayheadCriterion($this->fromPlayhead);
        }

        if ($this->fromIndex !== null) {
            $criteria[] = new FromIndexCriterion($this->fromIndex);
        }

        if ($this->archived !== null) {
            $criteria[] = new ArchivedCriterion($this->archived);
        }

        return new Criteria(...$criteria);
    }
}
