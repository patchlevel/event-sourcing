<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projector\MetadataProjectorResolver;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorResolver;

final class ProjectorTarget implements Target
{
    public function __construct(
        private readonly object $projector,
        private readonly ProjectorResolver $projectorResolver = new MetadataProjectorResolver(),
    ) {
    }

    public function save(Message ...$messages): void
    {
        foreach ($messages as $message) {
            $subscribeMethod = $this->projectorResolver->resolveSubscribeMethod($this->projector, $message);

            if (!$subscribeMethod) {
                continue;
            }

            $subscribeMethod($message);
        }
    }
}
