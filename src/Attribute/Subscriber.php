<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;
use Patchlevel\EventSourcing\Subscription\RunMode;

#[Attribute(Attribute::TARGET_CLASS)]
class Subscriber
{
    public function __construct(
        public readonly string $id,
        public readonly RunMode $runMode,
        public readonly string $group = 'default',
    ) {
    }
}
