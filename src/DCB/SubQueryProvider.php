<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

use Patchlevel\EventSourcing\Store\SubQuery;

/** @experimental */
interface SubQueryProvider
{
    public function subQuery(): SubQuery;
}
