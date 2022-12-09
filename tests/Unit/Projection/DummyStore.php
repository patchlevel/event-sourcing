<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCollection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionNotFound;
use Patchlevel\EventSourcing\Projection\Projection\Store\ProjectionStore;

use function array_key_exists;
use function array_values;

final class DummyStore implements ProjectionStore
{
    /** @var array<string, Projection> */
    private array $projections = [];
    /** @var list<Projection> */
    public array $savedProjections = [];
    /** @var list<ProjectionId> */
    public array $removedProjectionIds = [];

    /**
     * @param list<Projection> $projections
     */
    public function __construct(array $projections = [])
    {
        foreach ($projections as $projection) {
            $this->projections[$projection->id()->toString()] = $projection;
        }
    }

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
            $this->savedProjections[] = clone $projection;
        }
    }

    public function remove(ProjectionId ...$projectionIds): void
    {
        foreach ($projectionIds as $projectionId) {
            $this->removedProjectionIds[] = $projectionId;
            unset($this->projections[$projectionId->toString()]);
        }
    }
}
