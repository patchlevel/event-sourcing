<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use function sprintf;

final class LockingNotImplemented extends StoreException
{
    /** @param class-string $platform */
    public function __construct(string $platform)
    {
        parent::__construct(
            sprintf(
                'Locking is not implemented on platform %s. Disable locking in the store options.',
                $platform,
            ),
        );
    }
}
