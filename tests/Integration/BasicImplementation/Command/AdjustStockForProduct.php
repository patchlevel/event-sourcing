<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Command;

use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\ProductId;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\StockId;

final readonly class AdjustStockForProduct
{
    public function __construct(
        #[Id]
        public StockId $stockId,
        public ProductId $productId,
        public int $quantity,
    ) {
    }
}
