<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository\StoreAdapter;

/**
 * The version of a stream as the store adapter understands it.
 * The repository does not interpret it, it only hands it back on the next save.
 *
 * @experimental
 */
final class Version
{
    /** @param int<0, max> $value */
    public function __construct(
        public readonly int $value,
    ) {
    }
}
