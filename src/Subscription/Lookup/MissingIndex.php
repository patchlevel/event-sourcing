<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Lookup;

use RuntimeException;

class MissingIndex extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Missing index header in current message');
    }
}
