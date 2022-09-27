<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Schema;

interface DryRunSchemaDirector extends SchemaDirector
{
    /**
     * @return list<string>
     */
    public function dryRunCreate(): array;

    /**
     * @return list<string>
     */
    public function dryRunUpdate(): array;

    /**
     * @return list<string>
     */
    public function dryRunDrop(): array;
}
