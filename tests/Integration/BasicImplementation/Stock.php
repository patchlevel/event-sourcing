<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\AutoInitialize;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Command\AdjustStockForProduct;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Command\DecreaseStockForProduct;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\StockAdjusted;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\StockCreated;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\StockDecreased;

#[Aggregate('stock')]
final class Stock extends BasicAggregateRoot
{
    #[Id]
    private StockId $id;

    /** @var array<string, int> */
    private array $stock;

    #[AutoInitialize]
    public static function initialize(StockId $id): static
    {
        $stock = new self();
        $stock->recordThat(new StockCreated($id));

        return $stock;
    }

    public function id(): StockId
    {
        return $this->id;
    }

    public function stockFor(ProductId $productId): int
    {
        return $this->stock[$productId->toString()] ?? 0;
    }

    #[Handle]
    public function decreaseStock(DecreaseStockForProduct $command): void
    {
        $this->recordThat(new StockDecreased($this->id, $command->productId, $command->quantity));
    }

    #[Handle]
    public function adjustStock(AdjustStockForProduct $command): void
    {
        $this->recordThat(new StockAdjusted($this->id, $command->productId, $command->quantity));
    }

    #[Apply]
    protected function applyStockCreated(StockCreated $event): void
    {
        $this->id = $event->stockId;
        $this->stock = [];
    }

    #[Apply]
    protected function applyStockDecreased(StockDecreased $event): void
    {
        $this->stock[$event->productId->toString()] = $this->stockFor($event->productId) - $event->quantity;
    }

    #[Apply]
    protected function applyStockAdjusted(StockAdjusted $event): void
    {
        $this->stock[$event->productId->toString()] = $event->quantity;
    }
}
