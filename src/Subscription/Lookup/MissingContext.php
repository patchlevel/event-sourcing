<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Lookup;

use RuntimeException;
use Throwable;

final class MissingContext extends RuntimeException
{
    public function __construct(Throwable|null $previous = null)
    {
        parent::__construct('Missing context in current message', 0, $previous);
    }
}
