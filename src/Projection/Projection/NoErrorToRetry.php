<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection;

use RuntimeException;

final class NoErrorToRetry extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No error to retry');
    }
}
