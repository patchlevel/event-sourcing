<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

class MetadataNotPossible extends AggregateException
{
    public function __construct()
    {
        parent::__construct('Metadata method must be called on the concrete implementation');
    }
}
