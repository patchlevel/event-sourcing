<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projectionist;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Message;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

final class ProjectionistEventBus implements EventBus
{
    public function __construct(
        private readonly Projectionist $projectionist,
        private readonly LockFactory $lockFactory,
        private readonly bool $throwByError = true,
    ) {
    }

    public function dispatch(Message ...$messages): void
    {
        $lock = $this->lockFactory->createLock('projectionist-run');

        if (!$lock->acquire(true)) {
            return;
        }

        try {
            $this->projectionist->run(throwByError: $this->throwByError);
        } finally {
            $lock->release();
        }
    }

    public static function createWithDefaultLockStrategy(Projectionist $projectionist): self
    {
        return new self(
            $projectionist,
            new LockFactory(
                new FlockStore(),
            ),
        );
    }
}
