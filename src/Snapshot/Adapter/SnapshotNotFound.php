<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot\Adapter;

use Patchlevel\EventSourcing\Snapshot\SnapshotException;

use function sprintf;

final class SnapshotNotFound extends SnapshotException
{
    public function __construct(string $key)
    {
        parent::__construct(
            sprintf(
                'snapshot with the key "%s" not found',
                $key,
            ),
        );
    }
}
