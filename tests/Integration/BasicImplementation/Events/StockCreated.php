<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\StockId;

#[Event('stock.created')]
final readonly class StockCreated
{
    public function __construct(
        public StockId $stockId,
    ) {
    }
}
