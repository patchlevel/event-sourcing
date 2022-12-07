<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\Message;

final class SyncProjectorListener implements Listener
{
    public function __construct(
        private readonly ProjectorRepository $projectorRepository,
        private readonly ProjectorResolver $projectorResolver = new MetadataProjectorResolver(),
    ) {
    }

    public function __invoke(Message $message): void
    {
        (new ProjectorHelper($this->projectorResolver))
            ->handleMessage($message, ...$this->projectorRepository->projectors());
    }
}
