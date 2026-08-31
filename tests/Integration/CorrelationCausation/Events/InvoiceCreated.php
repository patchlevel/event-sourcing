<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\CorrelationCausation\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Tests\Integration\CorrelationCausation\InvoiceId;

#[Event('invoice.created')]
final class InvoiceCreated
{
    public function __construct(
        public InvoiceId $invoiceId,
        public string $product,
    ) {
    }
}
