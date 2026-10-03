<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Projection;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Projection\BasicProjection;
use Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Events\InvoiceCreated;

final class NextInvoiceNumber extends BasicProjection
{
    public function initialState(): int
    {
        return 1;
    }

    #[Apply]
    public function applyInvoiceCreated(int $state, InvoiceCreated $event): int
    {
        return $event->invoiceNumber + 1;
    }

    /** @return list<string> */
    protected function tagFilter(): array
    {
        return [];
    }

    protected function lastEventIsEnough(): bool
    {
        return true;
    }
}
