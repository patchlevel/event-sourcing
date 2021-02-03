<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use function sprintf;

class CorruptedMetadata extends StoreException
{
    public function __construct(
        string $expectedAggregateId,
        ?int $expectedPlayhead,
        string $actualAggregateId,
        ?int $actualPlayhead
    ) {
        parent::__construct(sprintf(
            'Corrupted metadata: %s:%s get %s:%s',
            $actualAggregateId,
            (string)$actualPlayhead,
            $expectedAggregateId,
            (string)$expectedPlayhead
        ));
    }
}
