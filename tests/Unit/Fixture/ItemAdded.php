<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('item_added')]
final class ItemAdded
{
    public function __construct(
        public string $productId,
        public int $quantity,
    ) {
    }
}
