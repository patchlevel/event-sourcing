<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Serializer\Normalizer\IdNormalizer;

#[Event('order_created')]
final class OrderCreated
{
    public function __construct(
        #[IdNormalizer]
        public OrderId $orderId,
    ) {
    }
}
