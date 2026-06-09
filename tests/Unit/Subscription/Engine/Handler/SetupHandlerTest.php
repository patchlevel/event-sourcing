<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Attribute\OnFailed;
use Patchlevel\EventSourcing\Attribute\RetryStrategy as RetryStrategyName;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup as SetupCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Handler\SetupHandler;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ClockBasedRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\NoRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use Patchlevel\EventSourcing\Subscription\ThrowableToErrorContextTransformer;
use Patchlevel\EventSourcing\Tests\Unit\Subscription\DummySubscriptionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

#[CoversClass(SetupHandler::class)]
final class SetupHandlerTest extends TestCase
{
    /** @param iterable<object> $subscribers */
    private function createHandler(
        MessageLoader $messageLoader,
        DummySubscriptionStore $store,
        array $subscribers = [],
        RetryStrategyRepository|null $retryStrategyRepository = null,
    ): SetupHandler {
        return new SetupHandler(
            $messageLoader,
            new SubscriptionManager($store),
            new MetadataSubscriberAccessorRepository($subscribers),
            $retryStrategyRepository ?? new RetryStrategyRepository([
                RetryStrategyRepository::DEFAULT_STRATEGY_NAME => new ClockBasedRetryStrategy(),
                'no_retry' => new NoRetryStrategy(),
            ]),
            new NullLogger(),
        );
    }

    public function testNothingToSetup(): void
    {
        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->never())->method('load');

        $store = new DummySubscriptionStore();
        $handler = $this->createHandler($messageLoader, $store);

        $result = $handler(new SetupCommand());

        $store->assertNoChanges();
        self::assertEquals([], $result->errors);
    }

    public function testSetupWithoutCreateMethod(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('lastIndex')->willReturn(1);

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::New),
        ]);

        $handler = $this->createHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new SetupCommand());

        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Booting),
        );
    }

    public function testSetupWithCreateMethod(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public bool $created = false;

            #[Setup]
            public function create(): void
            {
                $this->created = true;
            }
        };

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('lastIndex')->willReturn(1);

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::New),
        ]);

        $handler = $this->createHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new SetupCommand());

        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Booting),
        );

        self::assertTrue($subscriber->created);
    }

    public function testSetupWithCreateError(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public function __construct(
                public readonly RuntimeException $exception = new RuntimeException('ERROR'),
            ) {
            }

            #[Setup]
            public function create(): void
            {
                throw $this->exception;
            }
        };

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('lastIndex')->willReturn(1);

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId),
        ]);

        $handler = $this->createHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new SetupCommand());

        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $store->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                0,
                new SubscriptionError(
                    'ERROR',
                    Status::New,
                    ThrowableToErrorContextTransformer::transform($subscriber->exception),
                ),
            ),
        );
    }

    public function testSetupWithCreateErrorNoRetry(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        #[RetryStrategyName('no_retry')]
        class {
            public function __construct(
                public readonly RuntimeException $exception = new RuntimeException('ERROR'),
            ) {
            }

            #[Setup]
            public function create(): void
            {
                throw $this->exception;
            }
        };

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('lastIndex')->willReturn(1);

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId),
        ]);

        $handler = $this->createHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new SetupCommand());

        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $store->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Failed,
                0,
                new SubscriptionError(
                    'ERROR',
                    Status::New,
                    ThrowableToErrorContextTransformer::transform($subscriber->exception),
                ),
            ),
        );
    }

    public function testSetupWithCreateErrorRecoveryNotPossible(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        #[RetryStrategyName('no_retry')]
        class {
            public function __construct(
                public readonly RuntimeException $exception = new RuntimeException('ERROR'),
            ) {
            }

            #[Setup]
            public function create(): void
            {
                throw $this->exception;
            }

            #[OnFailed]
            public function onFailed(): void
            {
            }
        };

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('lastIndex')->willReturn(1);

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId),
        ]);

        $handler = $this->createHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new SetupCommand());

        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $store->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Failed,
                0,
                new SubscriptionError(
                    'ERROR',
                    Status::New,
                    ThrowableToErrorContextTransformer::transform($subscriber->exception),
                ),
            ),
        );
    }

    public function testSetupWithSkipBooting(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('lastIndex')->willReturn(1);

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::New),
        ]);

        $handler = $this->createHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new SetupCommand(skipBooting: true));

        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active),
        );
    }

    public function testSetupWithFromNow(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromNow)]
        class {
        };

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('lastIndex')->willReturn(1);

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromNow, Status::New),
        ]);

        $handler = $this->createHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new SetupCommand());

        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromNow, Status::Active, 1),
        );
    }

    public function testSetupWithFromNowWithEmptyStream(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromNow)]
        class {
        };

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('lastIndex')->willReturn(0);

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromNow, Status::New),
        ]);

        $handler = $this->createHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new SetupCommand());

        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromNow, Status::Active, 0),
        );
    }
}
