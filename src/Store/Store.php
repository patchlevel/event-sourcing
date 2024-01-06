<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Closure;
use Patchlevel\EventSourcing\EventBus\Message;

interface Store
{
    public function load(
        Criteria|null $criteria = null,
        int|null $limit = null,
        int|null $offset = null,
        bool $backwards = false,
    ): Stream;

    public function count(Criteria|null $criteria = null): int;

    /**
     * @throws MissingDataForStorage
     * @throws UniqueConstraintViolation
     */
    public function save(Message ...$messages): void;

    /**
     * @param Closure():ClosureReturn $function
     *
     * @template ClosureReturn
     */
    public function transactional(Closure $function): void;
}
