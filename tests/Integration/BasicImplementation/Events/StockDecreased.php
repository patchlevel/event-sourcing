<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\ProductId;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\StockId;

#[Event('stock.decreased')]
final readonly class StockDecreased
{
    public function __construct(
        public StockId $stockId,
        public ProductId $productId,
        public int $quantity,
    ) {
    }
}
