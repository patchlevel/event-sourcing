<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Cleanup\Dbal;

final class DropTableTask
{
    public function __construct(
        public readonly string $table,
    ) {
    }
}
