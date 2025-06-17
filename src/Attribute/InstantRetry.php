<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;
use Throwable;

#[Attribute(Attribute::TARGET_CLASS)]
final class InstantRetry
{
    /**
     * @param positive-int|null                  $maxRetries
     * @param list<class-string<Throwable>>|null $exceptions
     */
    public function __construct(
        public readonly int|null $maxRetries = null,
        public readonly array|null $exceptions = null,
    ) {
    }
}
