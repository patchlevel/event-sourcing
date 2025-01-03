<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Test;

final class AggregateAlreadySet extends AggregateTestError
{
    public function __construct()
    {
        parent::__construct('Aggregate already set. You should only return the aggregate if there is no given present.');
    }
}
