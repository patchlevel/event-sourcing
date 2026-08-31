<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\CorrelationCausation\Processor;

use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Tests\Integration\CorrelationCausation\Events\OrderPlaced;
use Patchlevel\EventSourcing\Tests\Integration\CorrelationCausation\Invoice;
use Patchlevel\EventSourcing\Tests\Integration\CorrelationCausation\InvoiceId;

#[Processor('create_invoice')]
final class CreateInvoiceProcessor
{
    public function __construct(
        private readonly RepositoryManager $repositoryManager,
    ) {
    }

    #[Subscribe(OrderPlaced::class)]
    public function onOrderPlaced(OrderPlaced $event): void
    {
        $invoice = Invoice::create(InvoiceId::generate(), $event->product);

        $this->repositoryManager->get(Invoice::class)->save($invoice);
    }
}
