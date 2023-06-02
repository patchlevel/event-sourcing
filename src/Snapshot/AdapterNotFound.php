<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use function sprintf;

final class AdapterNotFound extends SnapshotException
{
    public function __construct(string $adapterName)
    {
        parent::__construct(
            sprintf(
                'adapter with the name "%s" not found',
                $adapterName,
            ),
        );
    }
}
