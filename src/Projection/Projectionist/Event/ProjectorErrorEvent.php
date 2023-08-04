<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projectionist\Event;

use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projector\Projector;
use Throwable;

final class ProjectorErrorEvent
{
    public function __construct(
        /** @var class-string<Projector> */
        public readonly string $projector,
        public readonly ProjectionId $projection,
        public readonly Throwable $error,
    ) {
    }
}
