<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

final class DefaultProjectorRepository implements ProjectorRepository
{
    /** @var array<string, Projector>|null */
    private ?array $projectorIdHashmap = null;

    /** @var array<string, Projector>|null */
    private ?array $projectorNameHashmap = null;

    /**
     * @param iterable<Projector> $projectors
     */
    public function __construct(
        private readonly iterable $projectors,
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

    public function findByProjectorName(string $name): ?Projector
    {
        if ($this->projectorNameHashmap === null) {
            $this->projectorNameHashmap = [];

            foreach ($this->projectors as $projector) {
                $this->projectorNameHashmap[$projector->projectorId()->name()] = $projector;
            }
        }

        return $this->projectorNameHashmap[$name] ?? null;
    }

    /**
     * @return list<Projector>
     */
    public function projectors(): array
    {
        return [...$this->projectors];
    }
}
