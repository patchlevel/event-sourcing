<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use function sprintf;

class InvalidStreamName extends StoreException
{
    public function __construct(string $streamName)
    {
        parent::__construct(sprintf('Invalid stream name "%s"', $streamName));
    }
}
