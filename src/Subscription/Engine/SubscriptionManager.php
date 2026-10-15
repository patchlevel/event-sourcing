<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Closure;
use Patchlevel\EventSourcing\Subscription\Store\PartitionInboxStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\TransactionCommitNotPossible;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use SplObjectStorage;
use Throwable;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function implode;
use function sprintf;
use function usort;

/** @internal */
final class SubscriptionManager
{
    /** @var SplObjectStorage<Subscription, null> */
    private SplObjectStorage $forAdd;

    /** @var SplObjectStorage<Subscription, null> */
    private SplObjectStorage $forUpdate;

    /** @var SplObjectStorage<Subscription, null> */
    private SplObjectStorage $forRemove;

    /** @var array<string, non-empty-list<int>> */
    private array $forInbox = [];

    public function __construct(
        private readonly SubscriptionStore $subscriptionStore,
        private readonly LoggerInterface|null $logger = null,
    ) {
        $this->forAdd = new SplObjectStorage();
        $this->forUpdate = new SplObjectStorage();
        $this->forRemove = new SplObjectStorage();
    }

    /**
     * Claims every subscription alone in its own transaction.
     *
     * @param Closure(Subscription):T            $closure
     * @param Closure(Subscription, Throwable):T $onError
     * @param Closure(Subscription):bool         $filter  decides on the unclaimed snapshot whether to claim at all
     *
     * @return list<T>
     *
     * @template T
     */
    public function forEachClaimed(
        SubscriptionCriteria $criteria,
        Closure $closure,
        Closure|null $onError = null,
        Closure|null $filter = null,
    ): array {
        $snapshot = $this->subscriptionStore->find($criteria);

        if ($filter !== null) {
            $snapshot = array_values(array_filter($snapshot, $filter));
        }

        $groupOnError = null;

        if ($onError !== null) {
            $groupOnError = static function (array $subscriptions, Throwable $e) use ($onError): mixed {
                /** @var non-empty-list<Subscription> $subscriptions */
                return $onError($subscriptions[0], $e);
            };
        }

        return $this->claimEach(
            $this->groups($snapshot, new ClaimGroups()),
            $criteria,
            static function (array $subscriptions) use ($closure): mixed {
                /** @var non-empty-list<Subscription> $subscriptions */
                return $closure($subscriptions[0]);
            },
            $groupOnError,
        );
    }

    /**
     * Claims subscriptions in groups in one transaction and passes them to the closure together. Partitions and other
     * subscriptions are never mixed, because the others have to read the whole stream while partitions only read their
     * inbox. A subscription that is locked by another worker is left out of its group.
     * If the closure throws, every subscription of the group goes into the error status.
     *
     * @param Closure(non-empty-list<Subscription>):T            $closure
     * @param Closure(non-empty-list<Subscription>, Throwable):T $onError
     *
     * @return list<T>
     *
     * @template T
     */
    public function forEachClaimedGroup(
        SubscriptionCriteria $criteria,
        ClaimGroups $groups,
        Closure $closure,
        Closure|null $onError = null,
    ): array {
        return $this->claimEach(
            $this->groups($this->subscriptionStore->find($criteria), $groups),
            $criteria,
            $closure,
            $onError,
        );
    }

    /**
     * @param list<non-empty-list<Subscription>>                 $groups
     * @param Closure(non-empty-list<Subscription>):T            $closure
     * @param Closure(non-empty-list<Subscription>, Throwable):T $onError
     *
     * @return list<T>
     *
     * @template T
     */
    private function claimEach(array $groups, SubscriptionCriteria $criteria, Closure $closure, Closure|null $onError): array
    {
        $results = [];

        foreach ($groups as $candidates) {
            $skipped = false;

            try {
                $outcome = $this->subscriptionStore->inLock(
                    /** @return T|null */
                    function () use ($candidates, $criteria, $closure, $onError, &$skipped): mixed {
                        $subscriptions = $this->claim($candidates, $criteria);

                        if ($subscriptions === []) {
                            $skipped = true;

                            return null;
                        }

                        try {
                            return $closure($subscriptions);
                        } catch (TransactionCommitNotPossible $e) {
                            throw $e;
                        } catch (Throwable $e) {
                            foreach ($subscriptions as $subscription) {
                                $this->logger?->error(sprintf(
                                    'Subscription Engine: Subscription "%s" failed: %s',
                                    $subscription->id(),
                                    $e->getMessage(),
                                ));

                                $subscription->error($e);
                                $this->update($subscription);
                            }

                            if ($onError === null) {
                                $skipped = true;

                                return null;
                            }

                            return $onError($subscriptions, $e);
                        } finally {
                            $this->flush();
                        }
                    },
                );
            } catch (TransactionCommitNotPossible $e) {
                $this->clearPending();

                $this->logger?->warning(sprintf(
                    'Subscription Engine: Subscription "%s" hit a transient error and will be retried on the next run: %s',
                    implode('", "', array_map(static fn (Subscription $s): string => $s->id(), $candidates)),
                    $e->getMessage(),
                ));

                continue;
            }

            if ($skipped) {
                continue;
            }

            /** @var T $result */
            $result = $outcome;
            $results[] = $result;
        }

        return $results;
    }

    /**
     * @param non-empty-list<Subscription> $candidates
     *
     * @return list<Subscription>
     */
    private function claim(array $candidates, SubscriptionCriteria $criteria): array
    {
        return $this->subscriptionStore->claim(
            array_map(static fn (Subscription $subscription): string => $subscription->id(), $candidates),
            $criteria,
        );
    }

    /**
     * Partitions are grouped in the order of the snapshot. Other subscriptions are grouped by position, so that only
     * subscriptions close to each other read the stream together. Without grouping, the order of the snapshot is kept.
     *
     * @param list<Subscription> $subscriptions
     *
     * @return list<non-empty-list<Subscription>>
     */
    private function groups(array $subscriptions, ClaimGroups $claimGroups): array
    {
        $partitionsPerClaim = $claimGroups->partitions;
        $subscriptionsPerClaim = $claimGroups->subscriptions;
        $positionWindow = $claimGroups->positionWindow;

        $groups = [];
        $partitions = [];
        $others = [];

        foreach ($subscriptions as $subscription) {
            if (!$subscription->isPartition()) {
                if ($subscriptionsPerClaim === 1) {
                    $groups[] = [$subscription];
                } else {
                    $others[] = $subscription;
                }

                continue;
            }

            $partitions[] = $subscription;

            if (count($partitions) < $partitionsPerClaim) {
                continue;
            }

            $groups[] = $partitions;
            $partitions = [];
        }

        if ($partitions !== []) {
            $groups[] = $partitions;
        }

        return [...$this->groupByPosition($others, $subscriptionsPerClaim, $positionWindow), ...$groups];
    }

    /**
     * @param list<Subscription> $subscriptions
     * @param positive-int       $size
     * @param positive-int       $window
     *
     * @return list<non-empty-list<Subscription>>
     */
    private function groupByPosition(array $subscriptions, int $size, int $window): array
    {
        usort(
            $subscriptions,
            static fn (Subscription $a, Subscription $b): int => ($a->position() ?? -1) <=> ($b->position() ?? -1),
        );

        $groups = [];
        $group = [];
        $lowest = null;

        foreach ($subscriptions as $subscription) {
            $position = $subscription->position() ?? -1;

            if ($group !== [] && (count($group) >= $size || $position - (int)$lowest > $window)) {
                $groups[] = $group;
                $group = [];
            }

            if ($group === []) {
                $lowest = $position;
            }

            $group[] = $subscription;
        }

        if ($group !== []) {
            $groups[] = $group;
        }

        return $groups;
    }

    /** @return list<Subscription> */
    public function find(SubscriptionCriteria $criteria): array
    {
        return $this->subscriptionStore->find($criteria);
    }

    public function add(Subscription ...$subscriptions): void
    {
        foreach ($subscriptions as $sub) {
            $this->forAdd->offsetSet($sub);
        }
    }

    public function update(Subscription ...$subscriptions): void
    {
        foreach ($subscriptions as $sub) {
            $this->forUpdate->offsetSet($sub);
        }
    }

    public function remove(Subscription ...$subscriptions): void
    {
        foreach ($subscriptions as $sub) {
            $this->forRemove->offsetSet($sub);
        }
    }

    /** Routes a message into the inbox of a partition. */
    public function addToInbox(string $subscriptionId, int $index): void
    {
        $this->inboxStore();
        $this->forInbox[$subscriptionId][] = $index;
    }

    /**
     * @param array<string, int> $afterIndex subscription id => position of the partition
     * @param positive-int       $limit
     *
     * @return array<string, non-empty-list<int>>
     */
    public function inbox(array $afterIndex, int $limit): array
    {
        if ($afterIndex === []) {
            return [];
        }

        return $this->inboxStore()->inbox($afterIndex, $limit);
    }

    public function supportsPartitions(): bool
    {
        return $this->subscriptionStore instanceof PartitionInboxStore;
    }

    private function inboxStore(): PartitionInboxStore
    {
        if (!$this->subscriptionStore instanceof PartitionInboxStore) {
            throw new PartitionsNotSupported($this->subscriptionStore::class);
        }

        return $this->subscriptionStore;
    }

    public function flush(): void
    {
        $toAdd = [];

        foreach ($this->forAdd as $subscription) {
            if ($this->forRemove->offsetExists($subscription)) {
                continue;
            }

            $toAdd[] = $subscription;
        }

        $toUpdate = [];

        foreach ($this->forUpdate as $subscription) {
            if ($this->forAdd->offsetExists($subscription)) {
                continue;
            }

            if ($this->forRemove->offsetExists($subscription)) {
                continue;
            }

            $toUpdate[] = $subscription;
        }

        $toRemove = [];

        foreach ($this->forRemove as $subscription) {
            if ($this->forAdd->offsetExists($subscription)) {
                continue;
            }

            $toRemove[] = $subscription;
        }

        // a group of partitions or a catching up router changes many subscriptions at once
        if ($toAdd !== []) {
            $this->subscriptionStore->add(...$toAdd);
        }

        if ($toUpdate !== []) {
            $this->subscriptionStore->update(...$toUpdate);
        }

        if ($toRemove !== []) {
            $this->subscriptionStore->remove(...$toRemove);
        }

        if ($this->forInbox !== []) {
            $this->inboxStore()->addToInbox($this->forInbox);
        }

        $this->clearPending();
    }

    private function clearPending(): void
    {
        $this->forAdd = new SplObjectStorage();
        $this->forUpdate = new SplObjectStorage();
        $this->forRemove = new SplObjectStorage();
        $this->forInbox = [];
    }
}
