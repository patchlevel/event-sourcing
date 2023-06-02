<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Closure;
use Patchlevel\EventSourcing\EventBus\Message;

interface Store
{
    public function load(?Criteria $criteria = null): Stream;

    public function count(?Criteria $criteria = null): int;

    public function save(Message ...$messages): void;

    /**
     * @param Closure():ClosureReturn $function
     *
     * @template ClosureReturn
     */
    public function transactional(Closure $function): void;
}
