<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\CorrelationCausation;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Tests\Integration\CorrelationCausation\Events\InvoiceCreated;

#[Aggregate('invoice')]
final class Invoice extends BasicAggregateRoot
{
    #[Id]
    private InvoiceId $id;
    private string $product;

    public static function create(InvoiceId $id, string $product): self
    {
        $self = new self();
        $self->recordThat(new InvoiceCreated($id, $product));

        return $self;
    }

    #[Apply(InvoiceCreated::class)]
    protected function applyInvoiceCreated(InvoiceCreated $event): void
    {
        $this->id = $event->invoiceId;
        $this->product = $event->product;
    }

    public function invoiceId(): InvoiceId
    {
        return $this->id;
    }

    public function product(): string
    {
        return $this->product;
    }
}
