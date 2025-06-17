<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

/** @deprecated use InstantRetry instead. */
#[Attribute(Attribute::TARGET_CLASS)]
final class RetryAggregateOutdated
{
    public function __construct(
        public readonly int $maxRetries = 3,
    ) {
    }
}
