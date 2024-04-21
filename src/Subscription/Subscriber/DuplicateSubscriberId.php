<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

use RuntimeException;

use function sprintf;

final class DuplicateSubscriberId extends RuntimeException
{
    public function __construct(string $subscriberId)
    {
        parent::__construct(sprintf('Duplicate subscriber id "%s".', $subscriberId));
    }
}
