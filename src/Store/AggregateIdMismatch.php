<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use function sprintf;

final class AggregateIdMismatch extends StoreException
{
    public function __construct(string $expected, string $value)
    {
        parent::__construct(sprintf('aggregate id mismatch: expected "%s", got "%s"', $expected, $value));
    }
}
