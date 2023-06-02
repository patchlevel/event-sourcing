<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

final class CriteriaBuilder
{
    private ?string $aggregateClass = null;
    private ?string $aggregateId = null;
    private ?int $limit = null;
    private ?int $fromIndex = null;
    private ?int $fromPlayhead = null;
    private ?bool $archived = null;

    public function aggregateClass(?string $aggregateClass): self
    {
        $this->aggregateClass = $aggregateClass;

        return $this;
    }

    public function aggregateId(?string $aggregateId): self
    {
        $this->aggregateId = $aggregateId;

        return $this;
    }

    public function limit(?int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function fromIndex(?int $fromIndex): self
    {
        $this->fromIndex = $fromIndex;

        return $this;
    }

    public function fromPlayhead(?int $fromPlayhead): self
    {
        $this->fromPlayhead = $fromPlayhead;

        return $this;
    }

    public function archived(?bool $archived): self
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
