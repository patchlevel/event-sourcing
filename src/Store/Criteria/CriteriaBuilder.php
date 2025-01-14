<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Criteria;

use function is_array;

final class CriteriaBuilder
{
    /** @var list<string>|null */
    private array|null $streamName = null;
    private string|null $aggregateName = null;
    private string|null $aggregateId = null;
    private int|null $fromIndex = null;
    private int|null $fromPlayhead = null;
    private int|null $toPlayhead = null;
    private bool|null $archived = null;

    /** @var list<string>|null */
    private array|null $events = null;

    /** @param string|list<string>|null $streamName */
    public function streamName(string|array|null $streamName): self
    {
        if ($streamName === null) {
            $this->streamName = null;

            return $this;
        }

        if (is_array($streamName)) {
            $this->streamName = $streamName;
        } else {
            $this->streamName = [$streamName];
        }

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

    public function toPlayhead(int|null $toPlayhead): self
    {
        $this->toPlayhead = $toPlayhead;

        return $this;
    }

    public function archived(bool|null $archived): self
    {
        $this->archived = $archived;

        return $this;
    }

    /** @param list<string>|null $events */
    public function events(array|null $events): self
    {
        $this->events = $events;

        return $this;
    }

    public function build(): Criteria
    {
        $criteria = [];

        if ($this->streamName !== null) {
            $criteria[] = new StreamCriterion(...$this->streamName);
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

        if ($this->toPlayhead !== null) {
            $criteria[] = new ToPlayheadCriterion($this->toPlayhead);
        }

        if ($this->fromIndex !== null) {
            $criteria[] = new FromIndexCriterion($this->fromIndex);
        }

        if ($this->archived !== null) {
            $criteria[] = new ArchivedCriterion($this->archived);
        }

        if ($this->events !== null) {
            $criteria[] = new EventsCriterion($this->events);
        }

        return new Criteria(...$criteria);
    }
}
