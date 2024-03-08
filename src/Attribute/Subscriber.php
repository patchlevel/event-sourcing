<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;
use Cspray\Phinal\AllowInheritance;
use Patchlevel\EventSourcing\Subscription\RunMode;

#[Attribute(Attribute::TARGET_CLASS)]
#[AllowInheritance('You can create specific attributes with default group and run mode')]
class Subscriber
{
    public function __construct(
        public readonly string $id,
        public readonly RunMode $runMode,
        public readonly string $group = 'default',
    ) {
    }
}
