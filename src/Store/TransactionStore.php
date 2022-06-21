<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Closure;

interface TransactionStore
{
    public function transactionBegin(): void;

    public function transactionCommit(): void;

    public function transactionRollback(): void;

    public function transactional(Closure $function): void;
}
