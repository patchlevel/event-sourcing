<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Cleanup\Dbal;

final class DropIndexTask
{
    public function __construct(
        public readonly string $index,
        public readonly string $table,
    ) {
    }
}
