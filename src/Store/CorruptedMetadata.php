<?php

namespace Patchlevel\EventSourcing\Store;

class CorruptedMetadata extends StoreException
{
    public function __construct(
        string $expectedAggregateId,
        string $expectedPlayhead,
        string $actualAggregateId,
        string $actualPlayhead
    ) {
        parent::__construct(sprintf(
            'Corrupted metadata: %s:%s get %s:%s',
            $actualAggregateId,
            $actualPlayhead,
            $expectedAggregateId,
            $expectedPlayhead
        ));
    }
}
