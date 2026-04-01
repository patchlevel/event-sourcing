<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Cleanup\Dbal;

final class DropTableTask
{
    /**
     * @param non-empty-string      $table
     * @param non-empty-string|null $connectionName
     */
    public function __construct(
        public readonly string $table,
        public readonly string|null $connectionName = null,
    ) {
    }
}
