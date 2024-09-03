<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicChildAggregate;
use Patchlevel\EventSourcing\Attribute\Apply;

final class Payment extends BasicChildAggregate
{
    private bool $payed = false;

    public function confirmPayment(): void
    {
        $this->recordThat(new PaymentConfirmed());
    }

    #[Apply(PaymentConfirmed::class)]
    public function applyItemAdded(PaymentConfirmed $event): void
    {
        $this->payed = true;
    }

    public function isPayed(): bool
    {
        return $this->payed;
    }
}
