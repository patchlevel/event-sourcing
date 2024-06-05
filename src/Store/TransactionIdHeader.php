<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

/** @psalm-immutable */
final class TransactionIdHeader
{
    public function __construct(
        public readonly int $transactionId,
    ) {
    }
}
