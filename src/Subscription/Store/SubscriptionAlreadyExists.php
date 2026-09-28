<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

use RuntimeException;
use Throwable;

use function sprintf;

final class SubscriptionAlreadyExists extends RuntimeException
{
    public function __construct(string $id, Throwable|null $previous = null)
    {
        parent::__construct(sprintf('Subscription "%s" already exists.', $id), 0, $previous);
    }
}
