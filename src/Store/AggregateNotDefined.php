<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use function sprintf;

final class AggregateNotDefined extends StoreException
{
    public function __construct(string $aggregate)
    {
        parent::__construct(sprintf('aggregate "%s" is not defined', $aggregate));
    }
}
