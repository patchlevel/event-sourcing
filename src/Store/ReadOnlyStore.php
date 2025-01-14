<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Closure;
use InvalidArgumentException;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Psr\Log\LoggerInterface;

final class ReadOnlyStore implements Store
{
    public function __construct(
        private readonly Store $store,
        private readonly LoggerInterface|null $logger = null,
    ) {
        if ($this->store instanceof StreamStore) {
            throw new InvalidArgumentException('store must not be a StreamStore. use StreamReadOnlyStore instead');
        }
    }

    public function load(
        Criteria|null $criteria = null,
        int|null $limit = null,
        int|null $offset = null,
        bool $backwards = false,
    ): Stream {
        return $this->store->load($criteria, $limit, $offset, $backwards);
    }

    public function count(Criteria|null $criteria = null): int
    {
        return $this->store->count($criteria);
    }

    public function save(Message ...$messages): void
    {
        foreach ($messages as $message) {
            $this->logger?->info('tried to save message in read only store', ['message' => $message]);
        }

        throw new StoreIsReadOnly();
    }

    public function transactional(Closure $function): void
    {
        $this->store->transactional($function);
    }
}
