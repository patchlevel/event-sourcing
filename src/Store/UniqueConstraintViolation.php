<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Throwable;

final class UniqueConstraintViolation extends StoreException
{
    public function __construct(Throwable|null $previous = null)
    {
        parent::__construct('unique constraint violation', 0, $previous);
    }
}
