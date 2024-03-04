<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use Patchlevel\EventSourcing\Metadata\Projector\AttributeProjectorMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Projector\ProjectorMetadataFactory;

use function array_values;

final class MetadataProjectorAccessorRepository implements ProjectorAccessorRepository
{
    /** @var array<string, ProjectorAccessor> */
    private array $projectorsMap = [];

    /** @param iterable<object> $projectors */
    public function __construct(
        private readonly iterable $projectors,
        private readonly ProjectorMetadataFactory $metadataFactory = new AttributeProjectorMetadataFactory(),
    ) {
    }

    /** @return iterable<ProjectorAccessor> */
    public function all(): iterable
    {
        return array_values($this->projectorAccessorMap());
    }

    public function get(string $id): ProjectorAccessor|null
    {
        $map = $this->projectorAccessorMap();

        return $map[$id] ?? null;
    }

    /** @return array<string, ProjectorAccessor> */
    private function projectorAccessorMap(): array
    {
        if ($this->projectorsMap !== []) {
            return $this->projectorsMap;
        }

        foreach ($this->projectors as $projector) {
            $metadata = $this->metadataFactory->metadata($projector::class);
            $this->projectorsMap[$metadata->id] = new MetadataProjectorAccessor($projector, $metadata);
        }

        return $this->projectorsMap;
    }
}
