<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projectionist;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Message;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

final class SyncProjectionistEventBusWrapper implements EventBus
{
    public function __construct(
        private readonly EventBus      $parentEventBus,
        private readonly Projectionist $projectionist,
        private readonly LockFactory   $lockFactory,
    )
    {
    }

    public function dispatch(Message ...$messages): void
    {
        $this->parentEventBus->dispatch(...$messages);

        $lock = $this->lockFactory->createLock('projectionist-run');

        if (!$lock->acquire(true)) {
            return;
        }

        try {
            $this->projectionist->run();
        } finally {
            $lock->release();
        }
    }

    public static function createWithDefaultLockStrategy(EventBus $parentEventBus, Projectionist $projectionist): self
    {
        return new self(
            $parentEventBus,
            $projectionist,
            new LockFactory(
                new FlockStore()
            )
        );
    }
}
