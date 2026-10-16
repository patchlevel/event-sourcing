<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;
use Patchlevel\EventSourcing\Subscription\RunMode;

use function str_contains;

#[Attribute(Attribute::TARGET_CLASS)]
class Subscriber
{
    public function __construct(
        public readonly string $id,
        public readonly RunMode $runMode,
        public readonly string $group = 'default',
    ) {
        // the separator of the partition ids, so a subscriber id never collides with one
        if (str_contains($id, '#')) {
            throw new InvalidSubscriberId($id);
        }
    }
}
