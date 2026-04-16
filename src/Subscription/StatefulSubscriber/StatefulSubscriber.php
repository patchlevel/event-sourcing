<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\StatefulSubscriber;

use LogicException;
use Patchlevel\EventSourcing\Subscription\Subscriber\BatchableSubscriber;
use Patchlevel\Hydrator\Attribute\Ignore;
use ReflectionClass;

use const PHP_VERSION_ID;

abstract class StatefulSubscriber implements BatchableSubscriber
{
    public function __construct(
        #[Ignore]
        private readonly StatefulSubscriberStore $store,
    ) {
        $this->store->load($this);
    }

    public function beginBatch(): void
    {
        // do nothing
    }

    public function commitBatch(): void
    {
        $this->store->store($this);
    }

    public function rollbackBatch(): void
    {
        $this->store->load($this);
    }

    public function forceCommit(): bool
    {
        return false;
    }

    public static function createLazy(StatefulSubscriberStore $store): static
    {
        if (PHP_VERSION_ID < 80400) {
            throw new LogicException('Lazy subscriber is only supported on PHP 8.4 or higher');
        }

        $reflection = new ReflectionClass(static::class);

        return $reflection->newLazyGhost(static function ($object) use ($store): void {
            $object->__construct($store);
        });
    }
}
