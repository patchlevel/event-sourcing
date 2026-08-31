<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Telemetry;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Tests\Integration\Telemetry\Events\OrderPlaced;

#[Aggregate('telemetry_order')]
final class Order extends BasicAggregateRoot
{
    #[Id]
    private OrderId $id;
    private string $product;

    public static function place(OrderId $id, string $product): self
    {
        $self = new self();
        $self->recordThat(new OrderPlaced($id, $product));

        return $self;
    }

    #[Apply(OrderPlaced::class)]
    protected function applyOrderPlaced(OrderPlaced $event): void
    {
        $this->id = $event->orderId;
        $this->product = $event->product;
    }

    public function orderId(): OrderId
    {
        return $this->id;
    }

    public function product(): string
    {
        return $this->product;
    }
}
