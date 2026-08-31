<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Telemetry\Events;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Tests\Integration\Telemetry\OrderId;

#[Event('telemetry.order.placed')]
final class OrderPlaced
{
    public function __construct(
        public OrderId $orderId,
        public string $product,
    ) {
    }
}
