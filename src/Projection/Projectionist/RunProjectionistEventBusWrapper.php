<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projectionist;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Message;

final class RunProjectionistEventBusWrapper implements EventBus
{
    public function __construct(
        private readonly EventBus $parentEventBus,
        private readonly Projectionist $projectionist
    ) {
    }

    public function dispatch(Message ...$messages): void
    {
        $this->parentEventBus->dispatch(...$messages);
        $this->projectionist->run();
    }
}
