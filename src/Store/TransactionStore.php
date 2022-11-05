<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Closure;

interface TransactionStore
{
    public function transactionBegin(): void;

    public function transactionCommit(): void;

    public function transactionRollback(): void;

    /**
     * @param Closure():ClosureReturn $function
     *
     * @template ClosureReturn
     */
    public function transactional(Closure $function): void;
}
