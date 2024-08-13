<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\ChildAggregate;
use Patchlevel\EventSourcing\Attribute\Id;
use RuntimeException;

#[Aggregate('order')]
final class Order extends BasicAggregateRoot
{
    #[Id]
    private OrderId $id;

    #[ChildAggregate]
    protected OrderItems $orderItems;

    #[ChildAggregate]
    protected Payment $payment;

    public function id(): OrderId
    {
        return $this->id;
    }

    public static function create(OrderId $id): self
    {
        $self = new self();
        $self->recordThat(new OrderCreated($id));

        return $self;
    }

    public function confirmPayment(): void
    {
        $this->payment->confirmPayment();
    }

    public function addItem(string $productId, int $quantity): void
    {
        if ($this->isPayed()) {
            throw new RuntimeException('No adding possible, already paid!');
        }

        $this->orderItems->addItem($productId, $quantity);
    }

    public function isPayed(): bool
    {
        return $this->payment->isPayed();
    }

    public function countItem(string $productId): int
    {
        return $this->orderItems->countItem($productId);
    }

    public function countAll(): int
    {
        return $this->orderItems->countAll();
    }

    #[Apply(OrderCreated::class)]
    protected function applyOrderCreated(OrderCreated $event): void
    {
        $this->id = $event->orderId;
        $this->orderItems = new OrderItems();
        $this->payment = new Payment();
    }
}
