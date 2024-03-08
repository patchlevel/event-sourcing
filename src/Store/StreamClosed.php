<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use RuntimeException;

final class StreamClosed extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Stream is already closed.');
    }
}
