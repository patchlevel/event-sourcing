<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

interface ArchivableStore
{
    public function archiveMessages(string $aggregateName, string $aggregateId, int $untilPlayhead): void;
}
