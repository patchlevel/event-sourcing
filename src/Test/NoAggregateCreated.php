<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Test;

final class NoAggregateCreated extends AggregateTestError
{
    public function __construct()
    {
        parent::__construct('No aggregate set and no aggregate returned. Please provide given events or create the aggregate with the first action.');
    }
}
