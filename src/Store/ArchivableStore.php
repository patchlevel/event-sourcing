<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Aggregate\AggregateRootInterface;

interface ArchivableStore
{
    /**
     * @param class-string<AggregateRootInterface> $aggregate
     */
    public function archiveMessages(string $aggregate, string $id, int $untilPlayhead): void;
}
