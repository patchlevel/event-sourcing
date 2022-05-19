<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Clock;

use DateTimeImmutable;

interface Clock
{
    public function createDateTimeImmutable(): DateTimeImmutable;
}
