<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Cleanup\Dbal;

final class DropIndexTask
{
    /**
     * @param non-empty-string      $index
     * @param non-empty-string      $table
     * @param non-empty-string|null $connectionName
     */
    public function __construct(
        public readonly string $index,
        public readonly string $table,
        public readonly string|null $connectionName = null,
    ) {
    }
}
