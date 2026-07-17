<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use function sprintf;

final class LockCouldNotBeAcquired extends StoreException
{
    public static function byTimeout(int $id, int $timeout): self
    {
        return new self(sprintf(
            'The lock with id [%s] could not be acquired with a timeout of %d',
            $id,
            $timeout,
        ));
    }

    public static function byError(int $id): self
    {
        return new self(sprintf('There was an error when tried to get the lock with id [%s]', $id));
    }
}
