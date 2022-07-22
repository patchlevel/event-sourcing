<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\Projection\AttributeProjectionMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Projection\ProjectionMetadataFactory;

use function array_key_exists;

/** @deprecated use DefaultProjectionist */
final class MetadataAwareProjectionHandler implements ProjectionHandler
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

    public function handle(Message $message): void
    {
        $event = $message->event();

        foreach ($this->projections as $projection) {
            $metadata = $this->metadataFactor->metadata($projection::class);

            if (!array_key_exists($event::class, $metadata->handleMethods)) {
                continue;
            }

            $handleMethod = $metadata->handleMethods[$event::class];

            $projection->$handleMethod($message);
        }
    }

    public function create(): void
    {
        foreach ($this->projections as $projection) {
            $metadata = $this->metadataFactor->metadata($projection::class);
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
            $metadata = $this->metadataFactor->metadata($projection::class);
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
