<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;
use Patchlevel\EventSourcing\Projection\Projection\Store\InMemoryStore;
use Patchlevel\EventSourcing\Projection\Projection\Store\ProjectionStore;

final class DummyStore implements ProjectionStore
{
    private InMemoryStore $parentStore;

    /** @var list<Projection> */
    public array $addedProjections = [];

    /** @var list<Projection> */
    public array $updatedProjections = [];

    /** @var list<Projection> */
    public array $removedProjections = [];

    /** @param list<Projection> $projections */
    public function __construct(array $projections = [])
    {
        $this->parentStore = new InMemoryStore($projections);
    }

    public function get(string $projectionId): Projection
    {
        return $this->parentStore->get($projectionId);
    }

    /** @return list<Projection> */
    public function find(ProjectionCriteria|null $criteria = null): array
    {
        return $this->parentStore->find($criteria);
    }

    public function add(Projection $projection): void
    {
        $this->parentStore->add($projection);
        $this->addedProjections[] = clone $projection;
    }

    public function update(Projection $projection): void
    {
        $this->parentStore->update($projection);
        $this->updatedProjections[] = clone $projection;
    }

    public function remove(Projection $projection): void
    {
        $this->parentStore->remove($projection);
        $this->removedProjections[] = clone $projection;
    }
}
