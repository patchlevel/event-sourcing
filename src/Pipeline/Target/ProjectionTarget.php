<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\Projection\AttributeProjectionMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Projection\ProjectionMetadataFactory;
use Patchlevel\EventSourcing\Projection\Projection;

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

    public function save(Message $message): void
    {
        $metadata = $this->metadataFactory->metadata($this->projection::class);
        $event = $message->event();

        if (!array_key_exists($event::class, $metadata->handleMethods)) {
            return;
        }

        $handlerMetadata = $metadata->handleMethods[$event::class];
        $method = $handlerMetadata->methodName;

        if ($handlerMetadata->passMessage) {
            $this->projection->$method($message);

            return;
        }

        $this->projection->$method($event);
    }
}
