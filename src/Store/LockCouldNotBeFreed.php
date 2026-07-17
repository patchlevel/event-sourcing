<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use function sprintf;

final class LockCouldNotBeFreed extends StoreException
{
    public static function notExist(int $id): self
    {
        return new self(sprintf('The lock with id [%s] could not be freed as it does not exist', $id));
    }

    public static function notOurs(int $id): self
    {
        return new self(sprintf('The lock with id [%s] could not be freed as it is not ours', $id));
    }
}
