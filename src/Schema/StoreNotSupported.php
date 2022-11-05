<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Schema;

use Patchlevel\EventSourcing\Store\Store;
use RuntimeException;

use function sprintf;

/**
 * @deprecated
 */
final class StoreNotSupported extends RuntimeException
{
    /**
     * @param class-string $need
     */
    public function __construct(Store $store, string $need)
    {
        parent::__construct(
            sprintf(
                'store "%s" is not supported, need "%s"',
                $store::class,
                $need
            )
        );
    }
}
