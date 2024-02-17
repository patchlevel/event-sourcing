<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use Closure;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\Projector\AttributeProjectorMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Projector\ProjectorMetadataFactory;

use function array_map;
use function array_merge;

final class MetadataProjectorResolver implements ProjectorResolver
{
    public function __construct(
        private readonly ProjectorMetadataFactory $metadataFactory = new AttributeProjectorMetadataFactory(),
    ) {
    }

    public function resolveSetupMethod(object $projector): Closure|null
    {
        $metadata = $this->metadataFactory->metadata($projector::class);
        $method = $metadata->setupMethod;

        if ($method === null) {
            return null;
        }

        return $projector->$method(...);
    }

    public function resolveTeardownMethod(object $projector): Closure|null
    {
        $metadata = $this->metadataFactory->metadata($projector::class);
        $method = $metadata->teardownMethod;

        if ($method === null) {
            return null;
        }

        return $projector->$method(...);
    }

    /** @return iterable<Closure> */
    public function resolveSubscribeMethods(object $projector, Message $message): iterable
    {
        $event = $message->event();
        $metadata = $this->metadataFactory->metadata($projector::class);

        $methods = array_merge(
            $metadata->subscribeMethods[$event::class] ?? [],
            $metadata->subscribeMethods[Subscribe::ALL] ?? [],
        );

        return array_map(
            static fn (string $method) => $projector->$method(...),
            $methods,
        );
    }

    public function projectorId(object $projector): string
    {
        return $this->metadataFactory->metadata($projector::class)->id;
    }
}
