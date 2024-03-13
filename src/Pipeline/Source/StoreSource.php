<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Source;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Criteria;
use Patchlevel\EventSourcing\Store\CriteriaBuilder;
use Patchlevel\EventSourcing\Store\Store;
use Traversable;

final class StoreSource implements Source
{
    public function __construct(
        private readonly Store $store,
        private readonly int $fromIndex = 0,
    ) {
    }

    /** @return Traversable<Message> */
    public function load(): Traversable
    {
        return $this->store->load($this->criteria());
    }

    public function count(): int
    {
        return $this->store->count($this->criteria());
    }

    private function criteria(): Criteria
    {
        return (new CriteriaBuilder())->fromIndex($this->fromIndex)->build();
    }
}
