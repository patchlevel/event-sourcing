<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Reactivate as ReactivateCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Handler\ReactivateHandler;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use Patchlevel\EventSourcing\Tests\Unit\Subscription\DummySubscriptionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(ReactivateHandler::class)]
final class ReactivateHandlerTest extends TestCase
{
    /** @param iterable<object> $subscribers */
    private function createHandler(DummySubscriptionStore $store, array $subscribers = []): ReactivateHandler
    {
        return new ReactivateHandler(
            new SubscriptionManager($store),
            new MetadataSubscriberAccessorRepository($subscribers),
            new NullLogger(),
        );
    }

    public function testReactivateError(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $store = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                0,
                new SubscriptionError('ERROR', Status::New),
            ),
        ]);

        $handler = $this->createHandler($store, [$subscriber]);
        $result = $handler(new ReactivateCommand());

        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::New, 0),
        );
    }

    public function testReactivateDetached(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Detached),
        ]);

        $handler = $this->createHandler($store, [$subscriber]);
        $result = $handler(new ReactivateCommand());

        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active),
        );
    }

    public function testReactivatePaused(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Paused),
        ]);

        $handler = $this->createHandler($store, [$subscriber]);
        $result = $handler(new ReactivateCommand());

        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active),
        );
    }

    public function testReactivateFinished(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Finished),
        ]);

        $handler = $this->createHandler($store, [$subscriber]);
        $result = $handler(new ReactivateCommand());

        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active),
        );
    }
}
