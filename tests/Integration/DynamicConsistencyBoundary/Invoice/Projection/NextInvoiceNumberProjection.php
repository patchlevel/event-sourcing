<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Invoice\Projection;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Projection\BasicProjection;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Invoice\Event\InvoiceCreated;

final class NextInvoiceNumberProjection extends BasicProjection
{
    /** @return list<string> */
    public function tagFilter(): array
    {
        return [];
    }

    public function initialState(): int
    {
        return 1;
    }

    #[Apply]
    public function applyInvoiceCreated(int $state, InvoiceCreated $event): int
    {
        return $event->invoiceNumber + 1;
    }

    public function lastEventIsEnough(): bool
    {
        return true;
    }
}
