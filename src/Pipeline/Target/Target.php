<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

interface Target
{
    public function save(AggregateChanged $event): void;
}
