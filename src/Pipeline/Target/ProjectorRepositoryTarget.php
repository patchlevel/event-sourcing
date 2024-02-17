<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projector\MetadataProjectorResolver;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorRepository;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorResolver;

final class ProjectorRepositoryTarget implements Target
{
    public function __construct(
        private readonly ProjectorRepository $projectorRepository,
        private readonly ProjectorResolver $projectorResolver = new MetadataProjectorResolver(),
    ) {
    }

    public function save(Message ...$messages): void
    {
        $projectors = $this->projectorRepository->projectors();

        foreach ($messages as $message) {
            foreach ($projectors as $projector) {
                $subscribeMethods = $this->projectorResolver->resolveSubscribeMethods($projector, $message);

                foreach ($subscribeMethods as $subscribeMethod) {
                    $subscribeMethod($message);
                }
            }
        }
    }
}
