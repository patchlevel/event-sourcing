<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

class AppendConditionNotMet extends StoreException
{
    public function __construct(
        public readonly AppendCondition $appendCondition,
    ) {
        parent::__construct('Append condition not met');
    }
}
