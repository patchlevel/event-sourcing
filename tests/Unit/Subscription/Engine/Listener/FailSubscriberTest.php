<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Listener;

use Patchlevel\EventSourcing\Attribute\OnFailed;
use Patchlevel\EventSourcing\Attribute\RetryStrategy as RetryStrategyName;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageError;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\FailSubscriber;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscriber\BatchableSubscriber;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use Patchlevel\EventSourcing\Subscription\ThrowableToErrorContextTransformer;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Tests\Unit\Subscription\DummySubscriptionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

#[CoversClass(FailSubscriber::class)]
final class FailSubscriberTest extends TestCase
{
    /** @return array{FailSubscriber, SubscriptionManager} */
    private function createListener(DummySubscriptionStore $store, array $subscribers = []): array
    {
        $subscriptionManager = new SubscriptionManager($store);

        return [
            new FailSubscriber(
                $subscriptionManager,
                new MetadataSubscriberAccessorRepository($subscribers),
                new NullLogger(),
            ),
            $subscriptionManager,
        ];
    }

    public function testDoesNothingWhenTransitionToFailedIsFalse(): void
    {
        $subscription = new Subscription('test', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active);
        $store = new DummySubscriptionStore([$subscription]);

        [$listener, $subscriptionManager] = $this->createListener($store);
        $listener->onHandleMessageError(new OnHandleMessageError(
            $subscription,
            new RuntimeException('ERROR'),
            new Message(new ProfileVisited(ProfileId::fromString('test'))),
            1,
            transitionToFailed: false,
        ));
        $subscriptionManager->flush();

        $store->assertNoChanges();
    }

    public function testFailsSubscriptionWhenNoSubscriberFound(): void
    {
        $exception = new RuntimeException('ERROR');
        $subscription = new Subscription('test', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active);
        $store = new DummySubscriptionStore([$subscription]);

        [$listener, $subscriptionManager] = $this->createListener($store);
        $listener->onHandleMessageError(new OnHandleMessageError(
            $subscription,
            $exception,
            new Message(new ProfileVisited(ProfileId::fromString('test'))),
            1,
            transitionToFailed: true,
        ));
        $subscriptionManager->flush();

        $store->assertUpdated(
            new Subscription(
                'test',
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Failed,
                null,
                new SubscriptionError('ERROR', Status::Active, ThrowableToErrorContextTransformer::transform($exception)),
            ),
        );
    }

    public function testFailsSubscriptionForBatchableSubscriber(): void
    {
        $exception = new RuntimeException('ERROR');

        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        #[RetryStrategyName('no_retry')]
        class implements BatchableSubscriber {
            #[Subscribe(ProfileVisited::class)]
            public function handle(): void
            {
            }

            #[OnFailed]
            public function onFailed(): void
            {
            }

            public function beginBatch(): void
            {
            }

            public function commitBatch(): void
            {
            }

            public function rollbackBatch(): void
            {
            }

            public function forceCommit(): bool
            {
                return false;
            }
        };

        $subscription = new Subscription('test', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Booting);
        $store = new DummySubscriptionStore([$subscription]);

        [$listener, $subscriptionManager] = $this->createListener($store, [$subscriber]);
        $listener->onHandleMessageError(new OnHandleMessageError(
            $subscription,
            $exception,
            new Message(new ProfileVisited(ProfileId::fromString('test'))),
            1,
            transitionToFailed: true,
        ));
        $subscriptionManager->flush();

        $store->assertUpdated(
            new Subscription(
                'test',
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Failed,
                null,
                new SubscriptionError('ERROR', Status::Booting, ThrowableToErrorContextTransformer::transform($exception)),
            ),
        );
    }

    public function testFailsSubscriptionWithoutFailedMethod(): void
    {
        $exception = new RuntimeException('ERROR');

        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        #[RetryStrategyName('no_retry')]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function handle(): void
            {
            }
        };

        $subscription = new Subscription('test', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active);
        $store = new DummySubscriptionStore([$subscription]);

        [$listener, $subscriptionManager] = $this->createListener($store, [$subscriber]);
        $listener->onHandleMessageError(new OnHandleMessageError(
            $subscription,
            $exception,
            new Message(new ProfileVisited(ProfileId::fromString('test'))),
            1,
            transitionToFailed: true,
        ));
        $subscriptionManager->flush();

        $store->assertUpdated(
            new Subscription(
                'test',
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Failed,
                null,
                new SubscriptionError('ERROR', Status::Active, ThrowableToErrorContextTransformer::transform($exception)),
            ),
        );
    }

    public function testRecoverySucceeds(): void
    {
        $exception = new RuntimeException('ERROR');

        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        #[RetryStrategyName('no_retry')]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function handle(): void
            {
            }

            #[OnFailed]
            public function onFailed(): void
            {
            }
        };

        $subscription = new Subscription('test', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active);
        $store = new DummySubscriptionStore([$subscription]);

        [$listener, $subscriptionManager] = $this->createListener($store, [$subscriber]);
        $listener->onHandleMessageError(new OnHandleMessageError(
            $subscription,
            $exception,
            new Message(new ProfileVisited(ProfileId::fromString('test'))),
            1,
            transitionToFailed: true,
        ));
        $subscriptionManager->flush();

        $store->assertUpdated(
            new Subscription('test', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active, 1),
        );
    }

    public function testRecoveryFails(): void
    {
        $exception = new RuntimeException('ERROR');

        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        #[RetryStrategyName('no_retry')]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function handle(): void
            {
            }

            #[OnFailed]
            public function onFailed(): void
            {
                throw new RuntimeException('RECOVERY ERROR');
            }
        };

        $subscription = new Subscription('test', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active);
        $store = new DummySubscriptionStore([$subscription]);

        [$listener, $subscriptionManager] = $this->createListener($store, [$subscriber]);
        $listener->onHandleMessageError(new OnHandleMessageError(
            $subscription,
            $exception,
            new Message(new ProfileVisited(ProfileId::fromString('test'))),
            1,
            transitionToFailed: true,
        ));
        $subscriptionManager->flush();

        $store->assertUpdated(
            new Subscription(
                'test',
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Failed,
                null,
                new SubscriptionError('ERROR', Status::Active, ThrowableToErrorContextTransformer::transform($exception)),
            ),
        );
    }
}
