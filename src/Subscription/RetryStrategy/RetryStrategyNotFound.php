<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\RetryStrategy;

use RuntimeException;

use function sprintf;

final class RetryStrategyNotFound extends RuntimeException
{
    public function __construct(string $name)
    {
        parent::__construct(sprintf('Retry strategy with name "%s" not found', $name));
    }
}
