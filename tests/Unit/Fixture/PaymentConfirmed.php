<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('payment_confirmed')]
final class PaymentConfirmed
{
    public function __construct(
    ) {
    }
}
