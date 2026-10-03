<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message;

use RuntimeException;

final class StreamNotRewindable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Stream cannot be rewound because the underlying iterator is single-pass.');
    }
}
