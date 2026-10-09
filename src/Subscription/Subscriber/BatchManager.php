<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

use function array_key_exists;
use function array_values;

final class BatchManager
{
    /** @var array<string, Batch> */
    private array $batches = [];

    public function has(string $subscriptionId): bool
    {
        return array_key_exists($subscriptionId, $this->batches);
    }

    public function get(string $subscriptionId): Batch
    {
        if (!array_key_exists($subscriptionId, $this->batches)) {
            throw new BatchNotFound($subscriptionId);
        }

        return $this->batches[$subscriptionId];
    }

    public function add(Batch $batch): void
    {
        $this->batches[$batch->subscription->id()] = $batch;
    }

    public function remove(string $subscriptionId): void
    {
        unset($this->batches[$subscriptionId]);
    }

    /** @return list<Batch> */
    public function all(): array
    {
        return array_values($this->batches);
    }

    public function clear(): void
    {
        $this->batches = [];
    }
}
