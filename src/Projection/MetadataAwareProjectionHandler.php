<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\Projection\AttributeProjectionMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Projection\ProjectionMetadataFactory;
use Patchlevel\EventSourcing\Projection\Projector\MetadataProjectorResolver;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorResolver;

/** @deprecated use ProjectorHelper */
final class MetadataAwareProjectionHandler implements ProjectionHandler
{
    private ProjectionMetadataFactory $metadataFactory;

    private ProjectorResolver $resolver;

    /** @param iterable<Projection> $projections */
    public function __construct(private iterable $projections, ProjectionMetadataFactory|null $metadataFactory = null)
    {
        $this->metadataFactory = $metadataFactory ?? new AttributeProjectionMetadataFactory();
        $this->resolver = new MetadataProjectorResolver($this->metadataFactory);
    }

    public function handle(Message $message): void
    {
        foreach ($this->projections as $projection) {
            $handleMethod = $this->resolver->resolveHandleMethod($projection, $message);

            if (!$handleMethod) {
                continue;
            }

            $handleMethod($message);
        }
    }

    public function create(): void
    {
        foreach ($this->projections as $projection) {
            $createMethod = $this->resolver->resolveCreateMethod($projection);

            if (!$createMethod) {
                continue;
            }

            $createMethod();
        }
    }

    public function drop(): void
    {
        foreach ($this->projections as $projection) {
            $dropMethod = $this->resolver->resolveDropMethod($projection);

            if (!$dropMethod) {
                continue;
            }

            $dropMethod();
        }
    }

    /** @return iterable<Projection> */
    public function projections(): iterable
    {
        return $this->projections;
    }

    public function metadataFactory(): ProjectionMetadataFactory
    {
        return $this->metadataFactory;
    }
}
