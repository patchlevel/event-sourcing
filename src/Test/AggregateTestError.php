<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Test;

use RuntimeException;

abstract class AggregateTestError extends RuntimeException
{
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}