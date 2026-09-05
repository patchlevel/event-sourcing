<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Telemetry;

use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\StoreException;

use function sprintf;

final class StoreDoesNotSupport extends StoreException
{
    /** @param class-string $interface */
    public static function interface(Store $store, string $interface): self
    {
        return new self(sprintf(
            'The wrapped store "%s" does not implement "%s"',
            $store::class,
            $interface,
        ));
    }
}
