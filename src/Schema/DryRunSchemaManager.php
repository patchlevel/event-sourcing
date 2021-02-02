<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Schema;

use Patchlevel\EventSourcing\Store\Store;

interface DryRunSchemaManager extends SchemaManager
{
    /**
     * @return list<string>
     */
    public function dryRunCreate(Store $store): array;

    /**
     * @return list<string>
     */
    public function dryRunUpdate(Store $store): array;

    /**
     * @return list<string>
     */
    public function dryRunDrop(Store $store): array;
}
