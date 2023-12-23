<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projector\MetadataProjectorResolver;
use Patchlevel\EventSourcing\Projection\Projector\Projector;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorHelper;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorResolver;

final class ProjectorTarget implements Target
{
    public function __construct(
        private readonly Projector $projector,
        private readonly ProjectorResolver $projectorResolver = new MetadataProjectorResolver(),
    ) {
    }

    public function save(Message ...$messages): void
    {
        $helper = new ProjectorHelper($this->projectorResolver);

        foreach ($messages as $message) {
            $helper->handleMessage($message, $this->projector);
        }
    }
}
