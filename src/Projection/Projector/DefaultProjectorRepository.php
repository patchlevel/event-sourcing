<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;

use function array_filter;
use function array_values;

final class DefaultProjectorRepository implements ProjectorRepository
{
    /** @var array<string, StatefulProjector>|null */
    private ?array $projectionIdHashmap = null;

    /**
     * @param iterable<Projector> $projectors
     */
    public function __construct(
        private readonly iterable $projectors = [],
    ) {
    }

    public function findByProjectionId(ProjectionId $projectionId): ?StatefulProjector
    {
        if ($this->projectionIdHashmap === null) {
            $this->projectionIdHashmap = [];

            foreach ($this->projectors as $projector) {
                if (!$projector instanceof StatefulProjector) {
                    continue;
                }

                $this->projectionIdHashmap[$projector->projectionId()->toString()] = $projector;
            }
        }

        return $this->projectionIdHashmap[$projectionId->toString()] ?? null;
    }

    /**
     * @return list<Projector>
     */
    public function projectors(): array
    {
        return [...$this->projectors];
    }

    /**
     * @return list<StatefulProjector>
     */
    public function statefulProjectors(): array
    {
        return array_values(
            array_filter(
                [...$this->projectors],
                static fn (Projector $projector) => $projector instanceof StatefulProjector
            )
        );
    }
}
