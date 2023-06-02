<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Source;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Store\Criteria;
use Patchlevel\EventSourcing\Store\CriteriaBuilder;
use Patchlevel\EventSourcing\Store\Store;
use Traversable;

final class StoreSource implements Source
{
    private Store $store;
    private int $fromIndex;

    public function __construct(Store $store, int $fromIndex = 0)
    {
        $this->store = $store;
        $this->fromIndex = $fromIndex;
    }

    /**
     * @return Traversable<Message>
     */
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
