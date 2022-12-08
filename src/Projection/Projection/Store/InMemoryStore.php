<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection\Store;

use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCollection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionNotFound;

use function array_key_exists;
use function array_values;

final class InMemoryStore implements ProjectionStore
{
    /** @var array<string, Projection> */
    private array $projections = [];

    public function get(ProjectionId $projectionId): Projection
    {
        if (array_key_exists($projectionId->toString(), $this->projections)) {
            return $this->projections[$projectionId->toString()];
        }

        throw new ProjectionNotFound($projectionId);
    }

    public function all(): ProjectionCollection
    {
        return new ProjectionCollection(array_values($this->projections));
    }

    public function save(Projection ...$projections): void
    {
        foreach ($projections as $projection) {
            $this->projections[$projection->id()->toString()] = $projection;
        }
    }

    public function remove(ProjectionId ...$projectionIds): void
    {
        foreach ($projectionIds as $projectionId) {
            unset($this->projections[$projectionId->toString()]);
        }
    }
}
