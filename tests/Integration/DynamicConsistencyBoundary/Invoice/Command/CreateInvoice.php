<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\DynamicConsistencyBoundary\Invoice\Command;

final class CreateInvoice
{
    public function __construct(
        public readonly int $money,
    ) {
    }
}
