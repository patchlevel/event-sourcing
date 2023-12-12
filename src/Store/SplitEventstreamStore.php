<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

interface SplitEventstreamStore extends TransactionStore
{
    /** @param class-string<AggregateRoot> $aggregate */
    public function archiveMessages(string $aggregate, string $id, int $untilPlayhead): void;
}
