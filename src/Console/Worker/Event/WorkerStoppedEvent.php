<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Worker\Event;

use Patchlevel\EventSourcing\Console\Worker\Worker;

final class WorkerStoppedEvent
{
    public function __construct(
        public readonly Worker $worker
    ) {
    }
}
