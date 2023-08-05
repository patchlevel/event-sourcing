<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projectionist;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Message;
use Symfony\Component\Lock\LockFactory;

final class ProjectionistEventBusWrapper implements EventBus
{
    public function __construct(
        private readonly EventBus $parentEventBus,
        private readonly Projectionist $projectionist,
        private readonly LockFactory $lockFactory,
        private readonly bool $alwaysBoot = false,
    ) {
    }

    public function dispatch(Message ...$messages): void
    {
        $this->parentEventBus->dispatch(...$messages);

        $lock = $this->lockFactory->createLock('projectionist');

        if (!$lock->acquire(true)) {
            return;
        }

        try {
            if ($this->alwaysBoot) {
                $this->projectionist->boot();
            }

            $this->projectionist->run();
        } finally {
            $lock->release();
        }
    }
}
