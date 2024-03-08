<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

use RuntimeException;

use function sprintf;

final class SubscriptionAlreadyExists extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Subscription "%s" already exists.', $id));
    }
}
