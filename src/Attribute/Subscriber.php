<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscription;

#[Attribute(Attribute::TARGET_CLASS)]
final class Subscriber
{
    public function __construct(
        public readonly string $id,
        public readonly string $group = Subscription::DEFAULT_GROUP,
        public readonly RunMode $runMode = RunMode::FromBeginning,
    ) {
    }
}
