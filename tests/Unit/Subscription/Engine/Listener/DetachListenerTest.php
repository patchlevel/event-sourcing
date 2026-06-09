<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Listener;

use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\DetachListener;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Subscription\DummySubscriptionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(DetachListener::class)]
final class DetachListenerTest extends TestCase
{
    private function createListener(DummySubscriptionStore $store, array $subscribers = []): DetachListener
    {
        return new DetachListener(
            new SubscriptionManager($store),
            new MetadataSubscriberAccessorRepository($subscribers),
            new NullLogger(),
        );
    }

    public function testDetachesActiveSubscriptionWithoutSubscriber(): void
    {
        $subscription = new Subscription('test', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active);
        $store = new DummySubscriptionStore([$subscription]);

        $listener = $this->createListener($store);
        $listener(new OnCommand(new Run()));

        $store->assertUpdated(
            new Subscription('test', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Detached),
        );
    }

    public function testDoesNotDetachWhenSubscriberExists(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $subscription = new Subscription('test', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active);
        $store = new DummySubscriptionStore([$subscription]);

        $listener = $this->createListener($store, [$subscriber]);
        $listener(new OnCommand(new Run()));

        $store->assertNoChanges();
    }

    public function testIgnoresNonRunCommands(): void
    {
        $subscription = new Subscription('test', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active);
        $store = new DummySubscriptionStore([$subscription]);

        $listener = $this->createListener($store);
        $listener(new OnCommand(new Boot()));

        $store->assertNoChanges();
    }

    public function testDetachesPausedAndFinishedSubscriptions(): void
    {
        $paused = new Subscription('paused', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Paused);
        $finished = new Subscription('finished', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Finished);
        $store = new DummySubscriptionStore([$paused, $finished]);

        $listener = $this->createListener($store);
        $listener(new OnCommand(new Run()));

        $store->assertUpdated(
            new Subscription('paused', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Detached),
            new Subscription('finished', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Detached),
        );
    }
}
