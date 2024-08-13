<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicChildAggregate;
use Patchlevel\EventSourcing\Attribute\Apply;

final class OrderItems extends BasicChildAggregate
{
    /** @var array<string, int> */
    private array $items = [];

    public function addItem(string $productId, int $quantity): void
    {
        $this->recordThat(new ItemAdded($productId, $quantity));
    }

    #[Apply]
    public function applyItemAdded(ItemAdded $event): void
    {
        $this->items[$event->productId] = $event->quantity;
    }

    public function countItem(string $productId): int
    {
        return $this->items[$productId] ?? 0;
    }

    public function countAll(): int
    {
        $count = 0;

        foreach ($this->items as $quantity) {
            $count += $quantity;
        }

        return $count;
    }
}
