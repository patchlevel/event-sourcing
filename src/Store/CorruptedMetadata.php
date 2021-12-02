<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use function sprintf;

class CorruptedMetadata extends StoreException
{
    public function __construct(string $expectedId, string $actualId)
    {
        parent::__construct(sprintf(
            'Corrupted metadata: expected id is %s get %s',
            $expectedId,
            $actualId
        ));
    }
}
