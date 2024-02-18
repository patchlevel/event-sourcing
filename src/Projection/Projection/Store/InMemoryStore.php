<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection\Store;

use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionAlreadyExists;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionNotFound;

use function array_filter;
use function array_key_exists;
use function array_values;
use function in_array;

final class InMemoryStore implements ProjectionStore
{
    /** @var array<string, Projection> */
    private array $projections = [];

    /** @param list<Projection> $projections */
    public function __construct(array $projections = [])
    {
        foreach ($projections as $projection) {
            $this->projections[$projection->id()] = $projection;
        }
    }

    public function get(string $projectionId): Projection
    {
        if (array_key_exists($projectionId, $this->projections)) {
            return $this->projections[$projectionId];
        }

        throw new ProjectionNotFound($projectionId);
    }

    /** @return list<Projection> */
    public function find(ProjectionCriteria|null $criteria = null): array
    {
        $projections = array_values($this->projections);

        if ($criteria === null) {
            return $projections;
        }

        return array_values(
            array_filter(
                $projections,
                static function (Projection $projection) use ($criteria): bool {
                    if ($criteria->groups === null && $criteria->ids === null) {
                        return true;
                    }

                    $ids = $criteria->ids ?? [];
                    $groups = $criteria->groups ?? [];

                    return in_array($projection->id(), $ids, true)
                        || in_array($projection->group(), $groups, true);
                },
            ),
        );
    }

    public function add(Projection $projection): void
    {
        if (array_key_exists($projection->id(), $this->projections)) {
            throw new ProjectionAlreadyExists($projection->id());
        }

        $this->projections[$projection->id()] = $projection;
    }

    public function update(Projection $projection): void
    {
        if (!array_key_exists($projection->id(), $this->projections)) {
            throw new ProjectionNotFound($projection->id());
        }

        $this->projections[$projection->id()] = $projection;
    }

    public function remove(Projection $projection): void
    {
        unset($this->projections[$projection->id()]);
    }
}
