<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Projection\AttributeProjectionMetadataFactory;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Projection\ProjectionMetadataFactory;

use function array_key_exists;

final class ProjectionTarget implements Target
{
    private Projection $projection;
    private ProjectionMetadataFactory $metadataFactory;

    public function __construct(
        Projection $projection,
        ?ProjectionMetadataFactory $projectionMetadataFactory = null
    ) {
        $this->projection = $projection;
        $this->metadataFactory = $projectionMetadataFactory ?? new AttributeProjectionMetadataFactory();
    }

    public function save(EventBucket $bucket): void
    {
        $metadata = $this->metadataFactory->metadata($this->projection);
        $event = $bucket->event();

        if (!array_key_exists($event::class, $metadata->handleMethods)) {
            return;
        }

        $method = $metadata->handleMethods[$event::class];
        $this->projection->$method($event);
    }
}
