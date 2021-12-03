<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

final class AggregateChangeNotRecorded extends AggregateException
{
    public function __construct()
    {
        parent::__construct('The event could not be recorded.');
    }
}
