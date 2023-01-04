<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Closure;
use Patchlevel\EventSourcing\Aggregate\AggregateRootInterface;
use Patchlevel\EventSourcing\EventBus\Message;

interface Store
{
    /**
     * @param class-string<AggregateRootInterface> $aggregate
     *
     * @return list<Message>
     */
    public function load(string $aggregate, string $id, int $fromPlayhead = 0): array;

    /**
     * @param class-string<AggregateRootInterface> $aggregate
     */
    public function has(string $aggregate, string $id): bool;

    public function save(Message ...$messages): void;

    /**
     * @param Closure():ClosureReturn $function
     *
     * @template ClosureReturn
     */
    public function transactional(Closure $function): void;
}
