<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Closure;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\TransactionCommitNotPossible;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use SplObjectStorage;
use Throwable;

use function sprintf;

/** @internal */
final class SubscriptionManager
{
    /** @var SplObjectStorage<Subscription, null> */
    private SplObjectStorage $forAdd;

    /** @var SplObjectStorage<Subscription, null> */
    private SplObjectStorage $forUpdate;

    /** @var SplObjectStorage<Subscription, null> */
    private SplObjectStorage $forRemove;

    public function __construct(
        private readonly SubscriptionStore $subscriptionStore,
        private readonly LoggerInterface|null $logger = null,
    ) {
        $this->forAdd = new SplObjectStorage();
        $this->forUpdate = new SplObjectStorage();
        $this->forRemove = new SplObjectStorage();
    }

    /**
     * @param Closure(Subscription):T            $closure
     * @param Closure(Subscription, Throwable):T $onError
     *
     * @return list<T>
     *
     * @template T
     */
    public function forEachClaimed(SubscriptionCriteria $criteria, Closure $closure, Closure|null $onError = null): array
    {
        $snapshot = $this->subscriptionStore->find($criteria);

        $results = [];

        foreach ($snapshot as $candidate) {
            $skipped = false;

            try {
                $outcome = $this->subscriptionStore->inLock(
                    /** @return T|null */
                    function () use ($candidate, $criteria, $closure, $onError, &$skipped): mixed {
                        $subscription = $this->subscriptionStore->claim($candidate->id(), $criteria);

                        if ($subscription === null) {
                            $skipped = true;

                            return null;
                        }

                        try {
                            return $closure($subscription);
                        } catch (TransactionCommitNotPossible $e) {
                            throw $e;
                        } catch (Throwable $e) {
                            $this->logger?->error(sprintf(
                                'Subscription Engine: Subscription "%s" failed: %s',
                                $subscription->id(),
                                $e->getMessage(),
                            ));

                            $subscription->error($e);
                            $this->update($subscription);

                            if ($onError === null) {
                                $skipped = true;

                                return null;
                            }

                            return $onError($subscription, $e);
                        } finally {
                            $this->flush();
                        }
                    },
                );
            } catch (TransactionCommitNotPossible $e) {
                $this->clearPending();

                $this->logger?->warning(sprintf(
                    'Subscription Engine: Subscription "%s" hit a transient error and will be retried on the next run: %s',
                    $candidate->id(),
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

    public function flush(): void
    {
        foreach ($this->forAdd as $subscription) {
            if ($this->forRemove->offsetExists($subscription)) {
                continue;
            }

            $this->subscriptionStore->add($subscription);
        }

        foreach ($this->forUpdate as $subscription) {
            if ($this->forAdd->offsetExists($subscription)) {
                continue;
            }

            if ($this->forRemove->offsetExists($subscription)) {
                continue;
            }

            $this->subscriptionStore->update($subscription);
        }

        foreach ($this->forRemove as $subscription) {
            if ($this->forAdd->offsetExists($subscription)) {
                continue;
            }

            $this->subscriptionStore->remove($subscription);
        }

        $this->clearPending();
    }

    private function clearPending(): void
    {
        $this->forAdd = new SplObjectStorage();
        $this->forUpdate = new SplObjectStorage();
        $this->forRemove = new SplObjectStorage();
    }
}
