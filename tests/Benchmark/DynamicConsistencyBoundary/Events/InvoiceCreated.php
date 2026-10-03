<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\DynamicConsistencyBoundary\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\EventTag;

#[Event('invoice.created')]
final class InvoiceCreated
{
    public function __construct(
        #[EventTag(prefix: 'invoice')]
        public readonly int $invoiceNumber,
    ) {
    }
}
