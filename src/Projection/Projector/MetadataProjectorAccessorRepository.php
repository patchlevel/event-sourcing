<?php

namespace Patchlevel\EventSourcing\Projection\Projector;

use Patchlevel\EventSourcing\Metadata\Projector\AttributeProjectorMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Projector\ProjectorMetadataFactory;

final class MetadataProjectorAccessorRepository implements ProjectorAccessorRepository
{
    private bool $init = false;

    /**
     * @var array<string, ProjectorAccessor>
     */
    private array $projectorsMap = [];

    public function __construct(
        private readonly iterable $projectors,
        private readonly ProjectorMetadataFactory $metadataFactory = new AttributeProjectorMetadataFactory()
    ) {
    }

    /**
     * @return iterable<ProjectorAccessor>
     */
    public function all(): iterable
    {
        if ($this->init === false) {
            $this->init();
        }

        return array_values($this->projectorsMap);
    }

    public function get(string $id): ProjectorAccessor|null
    {
        if ($this->init === false) {
            $this->init();
        }

        return $this->projectorsMap[$id] ?? null;
    }

    private function init(): void
    {
        $this->init = true;

        foreach ($this->projectors as $projector) {
            $metadata = $this->metadataFactory->metadata($projector::class);
            $this->projectorsMap[$metadata->id] = new MetadataProjectorAccessor($projector, $metadata);
        }
    }
}