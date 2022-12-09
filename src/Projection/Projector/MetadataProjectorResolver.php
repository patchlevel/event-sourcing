<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use Closure;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\Projection\AttributeProjectionMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Projection\ProjectionMetadataFactory;
use Patchlevel\EventSourcing\Projection\Projection;

use function array_key_exists;

final class MetadataProjectorResolver implements ProjectorResolver
{
    public function __construct(
        private readonly ProjectionMetadataFactory $metadataFactory = new AttributeProjectionMetadataFactory()
    ) {
    }

    public function resolveCreateMethod(Projection $projector): ?Closure
    {
        $metadata = $this->metadataFactory->metadata($projector::class);
        $method = $metadata->createMethod;

        if (!$method) {
            return null;
        }

        return $projector->$method(...);
    }

    public function resolveDropMethod(Projection $projector): ?Closure
    {
        $metadata = $this->metadataFactory->metadata($projector::class);
        $method = $metadata->dropMethod;

        if (!$method) {
            return null;
        }

        return $projector->$method(...);
    }

    public function resolveHandleMethod(Projection $projector, Message $message): ?Closure
    {
        $event = $message->event();
        $metadata = $this->metadataFactory->metadata($projector::class);

        if (!array_key_exists($event::class, $metadata->handleMethods)) {
            return null;
        }

        $handleMethod = $metadata->handleMethods[$event::class];

        return $projector->$handleMethod(...);
    }
}
