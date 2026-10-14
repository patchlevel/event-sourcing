<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_METHOD)]
final class Partition
{
    public function __construct(
        public readonly int|null $shards = null,
    ) {
        if ($shards !== null && $shards < 1) {
            throw new InvalidArgumentException('The number of shards must be greater than 0.');
        }
    }
}
