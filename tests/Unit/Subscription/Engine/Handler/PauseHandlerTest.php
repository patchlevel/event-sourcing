<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Pause as PauseCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Handler\PauseHandler;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use Patchlevel\EventSourcing\Tests\Unit\Subscription\DummySubscriptionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(PauseHandler::class)]
final class PauseHandlerTest extends TestCase
{
    private function createHandler(DummySubscriptionStore $store): PauseHandler
    {
        return new PauseHandler(new SubscriptionManager($store), new NullLogger());
    }

    public function testPauseBooting(): void
    {
        $subscriptionId = 'test';

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Booting),
        ]);

        $handler = $this->createHandler($store);
        $result = $handler(new PauseCommand());

        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Paused),
        );
    }

    public function testPauseActive(): void
    {
        $subscriptionId = 'test';

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active),
        ]);

        $handler = $this->createHandler($store);
        $result = $handler(new PauseCommand());

        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Paused),
        );
    }

    public function testPauseError(): void
    {
        $subscriptionId = 'test';

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

        $handler = $this->createHandler($store);
        $result = $handler(new PauseCommand());

        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Paused,
                0,
                new SubscriptionError('ERROR', Status::New),
            ),
        );
    }
}
