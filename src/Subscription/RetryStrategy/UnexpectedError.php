<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\RetryStrategy;

use RuntimeException;

final class UnexpectedError extends RuntimeException
{
}
