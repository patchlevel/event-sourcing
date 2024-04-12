<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Throwable;

final class Error
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $message,
        public readonly Throwable $throwable,
    ) {
    }
}
