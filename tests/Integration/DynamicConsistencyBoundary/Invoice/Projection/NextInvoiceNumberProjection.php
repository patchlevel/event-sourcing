<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Invoice\Projection;

use Patchlevel\EventSourcing\DCB\ApplyTrait;
use Patchlevel\EventSourcing\DCB\Projection;
use Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Invoice\Event\InvoiceCreated;

final class NextInvoiceNumberProjection implements Projection
{
    use ApplyTrait;

    /** @return list<string> */
    public function tagFilter(): array
    {
        return [];
    }

    public function initialState(): int
    {
        return 1;
    }

    public function applyInvoiceCreated(int $state, InvoiceCreated $event): int
    {
        return $state + 1;
    }
}
