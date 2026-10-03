<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscription;

final class SubscriberMetadata
{
    public function __construct(
        public readonly string $id,
        public readonly string $group = Subscription::DEFAULT_GROUP,
        public readonly RunMode $runMode = RunMode::FromBeginning,
        /** @var array<class-string|"*", SubscribeMethodMetadata> */
        public readonly array $subscribeMethods = [],
        public readonly string|null $setupMethod = null,
        public readonly string|null $teardownMethod = null,
        public readonly string|null $failedMethod = null,
        public readonly string|null $retryStrategy = null,
        public readonly string|null $cleanupMethod = null,
        public readonly bool $enableEventEmittingDuringBoot = false,
        public readonly bool $disableEventEmitting = false,
        public readonly BatchMetadata|null $batch = null,
    ) {
    }
}
