<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Closure;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\Projector\AttributeProjectorMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Projector\ProjectorMetadataFactory;

use function array_key_exists;

final class MetadataProjectorResolver implements ProjectorResolver
{
    public function __construct(
        private readonly ProjectorMetadataFactory $metadataFactory = new AttributeProjectorMetadataFactory()
    ) {
    }

    public function resolveCreateMethod(Projector $projector): ?Closure
    {
        $metadata = $this->metadataFactory->metadata($projector::class);
        $method = $metadata->createMethod;

        if (!$method) {
            return null;
        }

        return $projector->$method(...);
    }

    public function resolveDropMethod(Projector $projector): ?Closure
    {
        $metadata = $this->metadataFactory->metadata($projector::class);
        $method = $metadata->dropMethod;

        if (!$method) {
            return null;
        }

        return $projector->$method(...);
    }

    /**
     * @return (Closure(Message):void)|null
     */
    public function resolveHandleMethod(Projector $projector, Message $message): ?Closure
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
