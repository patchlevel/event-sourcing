<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Store\SubQuery;

/** @experimental */
interface SubQueryProvider
{
    public function subQuery(): SubQuery;
}
