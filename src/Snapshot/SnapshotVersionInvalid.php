<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use function sprintf;

final class SnapshotVersionInvalid extends SnapshotException
{
    public function __construct(string $key)
    {
        parent::__construct(
            sprintf(
                'snapshot version with the key "%s" is invalid',
                $key
            )
        );
    }
}
