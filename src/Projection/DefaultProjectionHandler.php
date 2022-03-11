<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

use function array_key_exists;

final class DefaultProjectionHandler implements ProjectionHandler
{
    /** @var iterable<Projection> */
    private iterable $projections;

    private ProjectionMetadataFactory $metadataFactor;

    /**
     * @param iterable<Projection> $projections
     */
    public function __construct(iterable $projections, ?ProjectionMetadataFactory $metadataFactory = null)
    {
        $this->projections = $projections;
        $this->metadataFactor = $metadataFactory ?? new AttributeProjectionMetadataFactory();
    }

    public function handle(AggregateChanged $event): void
    {
        foreach ($this->projections as $projection) {
            $metadata = $this->metadataFactor->metadata($projection);

            if (!array_key_exists($event::class, $metadata->handleMethods)) {
                continue;
            }

            $method = $metadata->handleMethods[$event::class];

            $projection->$method($event);
        }
    }

    public function create(): void
    {
        foreach ($this->projections as $projection) {
            $metadata = $this->metadataFactor->metadata($projection);
            $method = $metadata->createMethod;

            if (!$method) {
                continue;
            }

            $projection->$method();
        }
    }

    public function drop(): void
    {
        foreach ($this->projections as $projection) {
            $metadata = $this->metadataFactor->metadata($projection);
            $method = $metadata->dropMethod;

            if (!$method) {
                continue;
            }

            $projection->$method();
        }
    }

    /**
     * @return iterable<Projection>
     */
    public function projections(): iterable
    {
        return $this->projections;
    }

    public function metadataFactory(): ProjectionMetadataFactory
    {
        return $this->metadataFactor;
    }
}
