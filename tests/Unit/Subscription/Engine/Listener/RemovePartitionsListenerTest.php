<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Listener;

use Closure;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptionRemoved;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\RemovePartitionsListener;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStats;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function array_map;

#[CoversClass(RemovePartitionsListener::class)]
final class RemovePartitionsListenerTest extends TestCase
{
    public function testRemovesThePartitionsOfTheMainSubscription(): void
    {
        $main = new Subscription('profile', status: Status::Active);
        $partitionA = Subscription::partition($main, 'a', 0);
        $partitionB = Subscription::partition($main, 'b', 0);
        $other = new Subscription('other', status: Status::Active);

        $store = new InMemorySubscriptionStore([$main, $partitionA, $partitionB, $other]);

        $this->removeMain($store, $main);

        self::assertSame(['profile', 'other'], $this->ids($store->find()));
    }

    /** A custom store that ignores the partitions criteria returns the main subscription as well. */
    public function testIgnoresTheMainSubscriptionFromAStoreWithoutPartitionsCriteria(): void
    {
        $main = new Subscription('profile', status: Status::Active);
        $partition = Subscription::partition($main, 'a', 0);

        $inner = new InMemorySubscriptionStore([$main, $partition]);
        $store = new class ($inner) implements SubscriptionStore {
            public function __construct(
                private readonly InMemorySubscriptionStore $inner,
            ) {
            }

            public function get(string $subscriptionId): Subscription
            {
                return $this->inner->get($subscriptionId);
            }

            /** @return list<Subscription> */
            public function find(SubscriptionCriteria|null $criteria = null): array
            {
                return $this->inner->find(new SubscriptionCriteria(ids: $criteria?->ids));
            }

            public function stats(SubscriptionCriteria $criteria): SubscriptionStats
            {
                return $this->inner->stats($criteria);
            }

            /**
             * @param non-empty-list<string> $ids
             *
             * @return list<Subscription>
             */
            public function claim(array $ids, SubscriptionCriteria $criteria, bool $wait = false): array
            {
                return $this->inner->claim($ids, $criteria, $wait);
            }

            public function add(Subscription ...$subscriptions): void
            {
                $this->inner->add(...$subscriptions);
            }

            public function update(Subscription ...$subscriptions): void
            {
                $this->inner->update(...$subscriptions);
            }

            public function remove(Subscription ...$subscriptions): void
            {
                $this->inner->remove(...$subscriptions);
            }

            /**
             * @param Closure():T $closure
             *
             * @return T
             *
             * @template T
             */
            public function inLock(Closure $closure): mixed
            {
                return $closure();
            }
        };

        $removed = $this->removeMain($store, $main);

        self::assertSame(['profile'], $this->ids($inner->find()));
        self::assertSame(['profile#a', 'profile'], $removed, 'the main subscription is not removed a second time');
    }

    /** @return list<string> ids of the subscriptions the removed event was dispatched for */
    private function removeMain(SubscriptionStore $store, Subscription $main): array
    {
        $subscriptionManager = new SubscriptionManager($store);
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new RemovePartitionsListener($subscriptionManager, $eventDispatcher));

        $removed = [];
        $eventDispatcher->addListener(
            OnSubscriptionRemoved::class,
            static function (OnSubscriptionRemoved $event) use (&$removed): void {
                $removed[] = $event->subscription->id();
            },
            -1,
        );

        $eventDispatcher->dispatch(new OnSubscriptionRemoved($main));
        $subscriptionManager->flush();

        return $removed;
    }

    /**
     * @param list<Subscription> $subscriptions
     *
     * @return list<string>
     */
    private function ids(array $subscriptions): array
    {
        return array_map(static fn (Subscription $subscription): string => $subscription->id(), $subscriptions);
    }
}
