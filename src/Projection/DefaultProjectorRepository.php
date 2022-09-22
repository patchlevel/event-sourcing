<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

final class DefaultProjectorRepository implements ProjectorRepository
{
    /** @var array<string, Projector>|null */
    private ?array $projectorIdHashmap = null;

    /**
     * @param iterable<Projector> $projectors
     */
    public function __construct(
        private readonly iterable $projectors = [],
    ) {
    }

    public function findByProjectorId(ProjectorId $projectorId): ?Projector
    {
        if ($this->projectorIdHashmap === null) {
            $this->projectorIdHashmap = [];

            foreach ($this->projectors as $projector) {
                $this->projectorIdHashmap[$projector->projectorId()->toString()] = $projector;
            }
        }

        return $this->projectorIdHashmap[$projectorId->toString()] ?? null;
    }

    /**
     * @return list<Projector>
     */
    public function projectors(): array
    {
        return [...$this->projectors];
    }
}
