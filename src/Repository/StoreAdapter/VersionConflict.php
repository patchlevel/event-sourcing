<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository\StoreAdapter;

use RuntimeException;
use Throwable;

/** @experimental */
final class VersionConflict extends RuntimeException
{
    public function __construct(Throwable|null $previous = null)
    {
        parent::__construct('The stream was changed in the meantime', 0, $previous);
    }
}
