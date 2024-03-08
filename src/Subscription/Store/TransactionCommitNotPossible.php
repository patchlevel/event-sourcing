<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

use RuntimeException;
use Throwable;

final class TransactionCommitNotPossible extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct(
            'Committing a transaction is not possible. Maybe your platform does not support transactional DDL.',
            0,
            $previous,
        );
    }
}
