<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\EventBus\Message;

final class ProjectorHelper
{
    public function __construct(
        private readonly ProjectorResolver $projectorResolver = new MetadataProjectorResolver()
    ) {
    }

    public function handleMessage(Message $message, Projector ...$projectors): void
    {
        foreach ($projectors as $projector) {
            $handleMethod = $this->projectorResolver->resolveHandleMethod($projector, $message);

            if (!$handleMethod) {
                continue;
            }

            $handleMethod($message);
        }
    }

    public function createProjection(Projector ...$projectors): void
    {
        foreach ($projectors as $projector) {
            $createMethod = $this->projectorResolver->resolveCreateMethod($projector);

            if (!$createMethod) {
                continue;
            }

            $createMethod();
        }
    }

    public function dropProjection(Projector ...$projectors): void
    {
        foreach ($projectors as $projector) {
            $dropMethod = $this->projectorResolver->resolveDropMethod($projector);

            if (!$dropMethod) {
                continue;
            }

            $dropMethod();
        }
    }
}
