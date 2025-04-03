<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Closure;
use Generator;
use Patchlevel\EventSourcing\Attribute\OnFailed;
use Patchlevel\EventSourcing\Attribute\RetryStrategy as RetryStrategyName;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\ArrayStream;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Engine\AlreadyProcessing;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\NoRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategy;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\LockableSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\BatchableSubscriber;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use Patchlevel\EventSourcing\Subscription\ThrowableToErrorContextTransformer;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\BatchingSubscriber;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Tests\Unit\Subscription\DummySubscriptionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use RuntimeException;

#[CoversClass(DefaultSubscriptionEngine::class)]
final class DefaultSubscriptionEngineTest extends TestCase
{
    use ProphecyTrait;

    public function testNothingToSetup(): void
    {
        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->shouldNotBeCalled();

        $store = new DummySubscriptionStore();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $store,
            new MetadataSubscriberAccessorRepository([]),
            logger: new NullLogger(),
        );

        $result = $engine->setup();

        $store->assertNoChanges();
        self::assertEquals([], $result->errors);
    }

    public function testSetupWithoutCreateMethod(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load(null, 1, null, true)->willReturn(new ArrayStream([$message1]))->shouldBeCalledOnce();

        $subscriptionStore = new DummySubscriptionStore();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->setup();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertAdded(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::New,
            ),
        );

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
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

        $subscriptionStore = new DummySubscriptionStore();

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load(null, 1, null, true)->willReturn(new ArrayStream([$message1]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->setup();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertAdded(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::New,
            ),
        );

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
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

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription($subscriptionId),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load(null, 1, null, true)->willReturn(new ArrayStream([$message1]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->setup();

        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
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

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription($subscriptionId),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load(null, 1, null, true)->willReturn(new ArrayStream([$message1]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->setup();

        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
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

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription($subscriptionId),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load(null, 1, null, true)->willReturn(new ArrayStream([$message1]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new NoRetryStrategy(),
            logger: new NullLogger(),
        );

        $result = $engine->setup();

        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
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

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::New,
            ),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load(null, 1, null, true)->willReturn(new ArrayStream([$message1]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->setup(null, true);

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        );
    }

    public function testSetupWithFromNow(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromNow)]
        class {
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromNow,
                Status::New,
            ),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load(null, 1, null, true)->willReturn(new ArrayStream([$message1]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->setup();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromNow,
                Status::Active,
                1,
            ),
        );
    }

    public function testSetupWithFromNowWithEmtpyStream(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromNow)]
        class {
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromNow,
                Status::New,
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load(null, 1, null, true)->willReturn(new ArrayStream([]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->setup();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromNow,
                Status::Active,
                0,
            ),
        );
    }

    public function testNothingToBoot(): void
    {
        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->shouldNotBeCalled();

        $store = new DummySubscriptionStore();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $store,
            new MetadataSubscriberAccessorRepository([]),
            logger: new NullLogger(),
        );

        $result = $engine->boot();

        self::assertEquals(0, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $store->assertNoChanges();
    }

    public function testBootDiscoverNewSubscribers(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->shouldNotBeCalled();

        $subscriptionStore = new DummySubscriptionStore();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->boot();

        self::assertEquals(0, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertAdded(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::New,
            ),
        );

        $subscriptionStore->assertNoUpdated();
    }

    public function testBootWithSubscriber(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->boot();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertNoAdded();

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
                1,
            ),
        );

        self::assertSame($message, $subscriber->message);
    }

    public function testBootWithError(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public function __construct(
                public readonly RuntimeException $exception = new RuntimeException('ERROR'),
            ) {
            }

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                throw $this->exception;
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->boot();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                0,
                new SubscriptionError(
                    'ERROR',
                    Status::Booting,
                    ThrowableToErrorContextTransformer::transform($subscriber->exception),
                ),
            ),
        );
    }

    public function testBootWithErrorNoRetry(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        #[RetryStrategyName('no_retry')]
        class {
            public function __construct(
                public readonly RuntimeException $exception = new RuntimeException('ERROR'),
            ) {
            }

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                throw $this->exception;
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->boot();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Failed,
                0,
                new SubscriptionError(
                    'ERROR',
                    Status::Booting,
                    ThrowableToErrorContextTransformer::transform($subscriber->exception),
                ),
            ),
        );
    }

    public function testBootWithErrorAndRecovery(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public function __construct(
                public readonly RuntimeException $exception = new RuntimeException('ERROR'),
            ) {
            }

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                throw $this->exception;
            }

            #[OnFailed]
            public function onFailed(): void
            {
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new NoRetryStrategy(),
            logger: new NullLogger(),
        );

        $result = $engine->boot();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
                1,
            ),
        );
    }

    public function testBootWithErrorAndRecoveryFailed(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public function __construct(
                public readonly RuntimeException $exception = new RuntimeException('ERROR'),
            ) {
            }

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                throw $this->exception;
            }

            #[OnFailed]
            public function onFailed(): void
            {
                throw new RuntimeException('RECOVERY ERROR');
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new NoRetryStrategy(),
            logger: new NullLogger(),
        );

        $result = $engine->boot();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Failed,
                0,
                new SubscriptionError(
                    'ERROR',
                    Status::Booting,
                    ThrowableToErrorContextTransformer::transform($subscriber->exception),
                ),
            ),
        );
    }

    public function testBootWithErrorAndRecoveryFailedBecauseBatching(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class implements BatchableSubscriber {
            public function __construct(
                public readonly RuntimeException $exception = new RuntimeException('ERROR'),
            ) {
            }

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                throw $this->exception;
            }

            #[OnFailed]
            public function onFailed(): void
            {
            }

            public function beginBatch(): void
            {
                // TODO: Implement beginBatch() method.
            }

            public function commitBatch(): void
            {
                // TODO: Implement commitBatch() method.
            }

            public function rollbackBatch(): void
            {
                // TODO: Implement rollbackBatch() method.
            }

            public function forceCommit(): bool
            {
                return false;
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new NoRetryStrategy(),
            logger: new NullLogger(),
        );

        $result = $engine->boot();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Failed,
                0,
                new SubscriptionError(
                    'ERROR',
                    Status::Booting,
                    ThrowableToErrorContextTransformer::transform($subscriber->exception),
                ),
            ),
        );
    }

    public function testBootWithLimit(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->boot(new SubscriptionEngineCriteria(), 1);

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(false, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertNoAdded();

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
                1,
            ),
        );

        self::assertSame($message, $subscriber->message);
    }

    public function testBootingWithSkip(): void
    {
        $subscriptionId1 = 'test1';
        $subscriber1 = new #[Subscriber('test1', RunMode::FromBeginning)]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $subscriptionId2 = 'test2';
        $subscriber2 = new #[Subscriber('test2', RunMode::FromBeginning)]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId1,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
            new Subscription(
                $subscriptionId2,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
                1,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber1, $subscriber2]),
            logger: new NullLogger(),
        );

        $result = $engine->boot();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId1,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
                1,
            ),
            new Subscription(
                $subscriptionId2,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
                1,
            ),
        );

        self::assertSame($message, $subscriber1->message);
        self::assertNull($subscriber2->message);
    }

    public function testBootingWithGabInIndex(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            /** @var list<Message> */
            public array $messages = [];

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->messages[] = $message;
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));
        $message2 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([
            1 => $message1,
            3 => $message2,
        ]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->boot();

        self::assertEquals(2, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
                3,
            ),
        );

        self::assertSame([$message1, $message2], $subscriber->messages);
    }

    public function testBootingWithOnlyOnce(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::Once)]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::Once,
                Status::Booting,
            ),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message1]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->boot();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::Once,
                Status::Finished,
                1,
            ),
        );

        self::assertEquals($message1, $subscriber->message);
    }

    public function testBootAlreadyProcessing(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public SubscriptionEngine|null $engine = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(): void
            {
                $this->engine?->boot();
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message1]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $subscriber->engine = $engine;

        $result = $engine->boot();

        self::assertCount(1, $result->errors);
        self::assertInstanceOf(AlreadyProcessing::class, $result->errors[0]->throwable);
    }

    public function testBootTwice(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();
        $streamableStore->load($this->criteria(1))->willReturn(new ArrayStream([]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->boot(limit: 1);

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(false, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertNoAdded();

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
                1,
            ),
        );

        self::assertSame($message, $subscriber->message);

        $subscriptionStore->reset();
        $result = $engine->boot();

        self::assertEquals(0, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
                1,
            ),
        );
    }

    public function testBootWithoutSubscriber(): void
    {
        $subscriptionId = 'test';

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([]),
            logger: new NullLogger(),
        );

        $result = $engine->boot();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertNoChanges();
    }

    public function testBootBatchingSuccess(): void
    {
        $subscriber = new BatchingSubscriber();

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->boot();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertNoAdded();

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
                1,
            ),
        );

        self::assertSame([$message], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(1, $subscriber->commitBatchCalled);
        self::assertSame(0, $subscriber->rollbackBatchCalled);
    }

    public function testBootBatchingSuccessForceCommit(): void
    {
        $subscriber = new BatchingSubscriber(
            forceCommitAfterMessages: 1,
        );

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));
        $message2 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([
            $message1,
            $message2,
        ]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->boot();

        self::assertEquals(2, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertNoAdded();

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
                2,
            ),
        );

        self::assertSame([$message1, $message2], $subscriber->receivedMessages);
        self::assertSame(2, $subscriber->beginBatchCalled);
        self::assertSame(2, $subscriber->commitBatchCalled);
        self::assertSame(0, $subscriber->rollbackBatchCalled);
    }

    public function testBootBatchingWithHandleError(): void
    {
        $subscriber = new BatchingSubscriber(
            throwForMessage: new RuntimeException('ERROR'),
        );

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->boot();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);

        $error = $result->errors[0];

        self::assertEquals($subscriber::ID, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                0,
                new SubscriptionError(
                    'ERROR',
                    Status::Booting,
                    ThrowableToErrorContextTransformer::transform($subscriber->throwForMessage),
                ),
            ),
        );

        self::assertSame([$message], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(0, $subscriber->commitBatchCalled);
        self::assertSame(1, $subscriber->rollbackBatchCalled);
    }

    public function testBootBatchingWithBeginBatchError(): void
    {
        $subscriber = new BatchingSubscriber(
            throwForBeginBatch: new RuntimeException('ERROR'),
        );

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->boot();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);

        $error = $result->errors[0];

        self::assertEquals($subscriber::ID, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                0,
                new SubscriptionError(
                    'ERROR',
                    Status::Booting,
                    ThrowableToErrorContextTransformer::transform($subscriber->throwForBeginBatch),
                ),
            ),
        );

        self::assertSame([], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(0, $subscriber->commitBatchCalled);
        self::assertSame(1, $subscriber->rollbackBatchCalled);
    }

    public function testBootBatchingWithCommitBatchError(): void
    {
        $subscriber = new BatchingSubscriber(
            throwForCommitBatch: new RuntimeException('ERROR'),
        );

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->boot();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);

        $error = $result->errors[0];

        self::assertEquals($subscriber::ID, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                0,
                new SubscriptionError(
                    'ERROR',
                    Status::Booting,
                    ThrowableToErrorContextTransformer::transform($subscriber->throwForCommitBatch),
                ),
            ),
        );

        self::assertSame([$message], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(1, $subscriber->commitBatchCalled);
        self::assertSame(0, $subscriber->rollbackBatchCalled);
    }

    public function testBootBatchingWithRollbackBatchError(): void
    {
        $subscriber = new BatchingSubscriber(
            throwForMessage: new RuntimeException('ERROR'),
            throwForRollbackBatch: new RuntimeException('ERROR'),
        );

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->boot();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);

        $error = $result->errors[0];

        self::assertEquals($subscriber::ID, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                0,
                new SubscriptionError(
                    'ERROR',
                    Status::Booting,
                    ThrowableToErrorContextTransformer::transform($subscriber->throwForMessage),
                ),
            ),
        );

        self::assertSame([$message], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(0, $subscriber->commitBatchCalled);
        self::assertSame(1, $subscriber->rollbackBatchCalled);
    }

    public function testRunDiscoverNewSubscribers(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $streamableStore = $this->prophesize(Store::class);
        $subscriptionStore = new DummySubscriptionStore();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->run();

        self::assertEquals(0, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertAdded(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::New,
            ),
        );
    }

    public function testRunning(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->run();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
                1,
            ),
        );

        self::assertSame($message, $subscriber->message);
    }

    public function testRunningWithLimit(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));
        $message2 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore
            ->load($this->criteria())
            ->willReturn(new ArrayStream([$message1, $message2]))
            ->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->run(new SubscriptionEngineCriteria(), 1);

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(false, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
                1,
            ),
        );

        self::assertSame($message1, $subscriber->message);
    }

    public function testRunningWithSkip(): void
    {
        $subscriptionId1 = 'test1';
        $subscriber1 = new #[Subscriber('test1', RunMode::FromBeginning)]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $subscriptionId2 = 'test2';
        $subscriber2 = new #[Subscriber('test2', RunMode::FromBeginning)]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId1,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
            new Subscription(
                $subscriptionId2,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
                1,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber1, $subscriber2]),
            logger: new NullLogger(),
        );

        $result = $engine->run();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId1,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
                1,
            ),
            new Subscription(
                $subscriptionId2,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
                1,
            ),
        );

        self::assertSame($message, $subscriber1->message);
        self::assertNull($subscriber2->message);
    }

    public function testRunningWithError(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public function __construct(
                public readonly RuntimeException $exception = new RuntimeException('ERROR'),
            ) {
            }

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                throw $this->exception;
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->run();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                0,
                new SubscriptionError(
                    'ERROR',
                    Status::Active,
                    ThrowableToErrorContextTransformer::transform($subscriber->exception),
                ),
            ),
        );
    }

    public function testRunningWithErrorNoRetry(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        #[RetryStrategyName('no_retry')]
        class {
            public function __construct(
                public readonly RuntimeException $exception = new RuntimeException('ERROR'),
            ) {
            }

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                throw $this->exception;
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->run();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Failed,
                0,
                new SubscriptionError(
                    'ERROR',
                    Status::Active,
                    ThrowableToErrorContextTransformer::transform($subscriber->exception),
                ),
            ),
        );
    }

    public function testRunningWithErrorAndRecovery(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public function __construct(
                public readonly RuntimeException $exception = new RuntimeException('ERROR'),
            ) {
            }

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                throw $this->exception;
            }

            #[OnFailed]
            public function onFailed(): void
            {
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new NoRetryStrategy(),
            logger: new NullLogger(),
        );

        $result = $engine->run();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
                1,
            ),
        );
    }

    public function testRunningWithErrorAndRecoveryFailed(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public function __construct(
                public readonly RuntimeException $exception = new RuntimeException('ERROR'),
            ) {
            }

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                throw $this->exception;
            }

            #[OnFailed]
            public function onFailed(): void
            {
                throw new RuntimeException('RECOVERY ERROR');
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new NoRetryStrategy(),
            logger: new NullLogger(),
        );

        $result = $engine->run();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Failed,
                0,
                new SubscriptionError(
                    'ERROR',
                    Status::Active,
                    ThrowableToErrorContextTransformer::transform($subscriber->exception),
                ),
            ),
        );
    }

    public function testRunningMarkDetached(): void
    {
        $subscriptionId = 'test';

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->shouldNotBeCalled();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([]),
            logger: new NullLogger(),
        );

        $result = $engine->run();

        self::assertEquals(0, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Detached,
                0,
            ),
        );
    }

    public function testRunningWithoutActiveSubscribers(): void
    {
        $subscriptionId = 'test';

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->shouldNotBeCalled();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([]),
            logger: new NullLogger(),
        );

        $result = $engine->run();

        self::assertEquals(0, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertNoChanges();
    }

    public function testRunningWithGabInIndex(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            /** @var list<Message> */
            public array $messages = [];

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->messages[] = $message;
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));
        $message2 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([
            1 => $message1,
            3 => $message2,
        ]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->run();

        self::assertEquals(2, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
                3,
            ),
        );

        self::assertSame([$message1, $message2], $subscriber->messages);
    }

    public function testRunningWithOnlyOnce(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::Once)]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::Once,
                Status::Active,
            ),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message1]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->run();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::Once,
                Status::Finished,
                1,
            ),
        );

        self::assertEquals($message1, $subscriber->message);
    }

    public function testRunningAlreadyProcessing(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public SubscriptionEngine|null $engine = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(): void
            {
                $this->engine?->run();
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message1]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $subscriber->engine = $engine;

        $result = $engine->run();

        self::assertCount(1, $result->errors);
        self::assertInstanceOf(AlreadyProcessing::class, $result->errors[0]->throwable);
    }

    public function testRunningTwice(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();
        $streamableStore->load($this->criteria(1))->willReturn(new ArrayStream([]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->run(limit: 1);

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(false, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertNoAdded();
        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
                1,
            ),
        );

        self::assertSame($message, $subscriber->message);

        $subscriptionStore->reset();
        $result = $engine->run();

        self::assertEquals(0, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertNoChanges();
    }

    public function testRunningBatchingSuccess(): void
    {
        $subscriber = new BatchingSubscriber();

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->run();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertNoAdded();
        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
                1,
            ),
        );

        self::assertSame([$message], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(1, $subscriber->commitBatchCalled);
        self::assertSame(0, $subscriber->rollbackBatchCalled);
    }

    public function testRunningBatchingSuccessForceCommit(): void
    {
        $subscriber = new BatchingSubscriber(
            forceCommitAfterMessages: 1,
        );

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));
        $message2 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([
            $message1,
            $message2,
        ]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->run();

        self::assertEquals(2, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $subscriptionStore->assertNoAdded();
        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
                2,
            ),
        );

        self::assertSame([$message1, $message2], $subscriber->receivedMessages);
        self::assertSame(2, $subscriber->beginBatchCalled);
        self::assertSame(2, $subscriber->commitBatchCalled);
        self::assertSame(0, $subscriber->rollbackBatchCalled);
    }

    public function testRunningBatchingWithHandleError(): void
    {
        $subscriber = new BatchingSubscriber(
            throwForMessage: new RuntimeException('ERROR'),
        );

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->run();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);

        $error = $result->errors[0];

        self::assertEquals($subscriber::ID, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                0,
                new SubscriptionError(
                    'ERROR',
                    Status::Active,
                    ThrowableToErrorContextTransformer::transform($subscriber->throwForMessage),
                ),
            ),
        );

        self::assertSame([$message], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(0, $subscriber->commitBatchCalled);
        self::assertSame(1, $subscriber->rollbackBatchCalled);
    }

    public function testRunningBatchingWithBeginBatchError(): void
    {
        $subscriber = new BatchingSubscriber(
            throwForBeginBatch: new RuntimeException('ERROR'),
        );

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->run();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);

        $error = $result->errors[0];

        self::assertEquals($subscriber::ID, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                0,
                new SubscriptionError(
                    'ERROR',
                    Status::Active,
                    ThrowableToErrorContextTransformer::transform($subscriber->throwForBeginBatch),
                ),
            ),
        );

        self::assertSame([], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(0, $subscriber->commitBatchCalled);
        self::assertSame(1, $subscriber->rollbackBatchCalled);
    }

    public function testRunningBatchingWithCommitBatchError(): void
    {
        $subscriber = new BatchingSubscriber(
            throwForCommitBatch: new RuntimeException('ERROR'),
        );

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->run();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);

        $error = $result->errors[0];

        self::assertEquals($subscriber::ID, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                0,
                new SubscriptionError(
                    'ERROR',
                    Status::Active,
                    ThrowableToErrorContextTransformer::transform($subscriber->throwForCommitBatch),
                ),
            ),
        );

        self::assertSame([$message], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(1, $subscriber->commitBatchCalled);
        self::assertSame(0, $subscriber->rollbackBatchCalled);
    }

    public function testRunningBatchingWithRollbackBatchError(): void
    {
        $subscriber = new BatchingSubscriber(
            throwForMessage: new RuntimeException('ERROR'),
            throwForRollbackBatch: new RuntimeException('ERROR'),
        );

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->run();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);

        $error = $result->errors[0];

        self::assertEquals($subscriber::ID, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                0,
                new SubscriptionError(
                    'ERROR',
                    Status::Active,
                    ThrowableToErrorContextTransformer::transform($subscriber->throwForMessage),
                ),
            ),
        );

        self::assertSame([$message], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(0, $subscriber->commitBatchCalled);
        self::assertSame(1, $subscriber->rollbackBatchCalled);
    }

    public function testTeardownDiscoverNewSubscribers(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $streamableStore = $this->prophesize(Store::class);
        $subscriptionStore = new DummySubscriptionStore();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->teardown();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertAdded(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::New,
            ),
        );
    }

    public function testTeardownWithoutTeardownMethod(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $subscription = new Subscription(
            $subscriptionId,
            Subscription::DEFAULT_GROUP,
            RunMode::FromBeginning,
            Status::Detached,
        );

        $subscriptionStore = new DummySubscriptionStore([$subscription]);

        $streamableStore = $this->prophesize(Store::class);

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->teardown();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertNoUpdated();
        $subscriptionStore->assertRemoved($subscription);
    }

    public function testTeardownWithSubscriber(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public Message|null $message = null;
            public bool $dropped = false;

            #[Teardown]
            public function drop(): void
            {
                $this->dropped = true;
            }
        };

        $subscription = new Subscription(
            $subscriptionId,
            Subscription::DEFAULT_GROUP,
            RunMode::FromBeginning,
            Status::Detached,
        );

        $subscriptionStore = new DummySubscriptionStore([$subscription]);

        $streamableStore = $this->prophesize(Store::class);

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->teardown();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertNoUpdated();
        $subscriptionStore->assertRemoved($subscription);
        self::assertTrue($subscriber->dropped);
    }

    public function testTeardownWithSubscriberAndError(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public Message|null $message = null;
            public bool $dropped = false;

            #[Teardown]
            public function drop(): void
            {
                throw new RuntimeException('ERROR');
            }
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Detached,
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->teardown();

        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertNoChanges();
    }

    public function testTeardownWithoutSubscriber(): void
    {
        $subscriberId = 'test';

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriberId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Detached,
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([]),
            logger: new NullLogger(),
        );

        $result = $engine->teardown();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertNoChanges();
    }

    public function testRemoveDiscoverNewSubscribers(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $streamableStore = $this->prophesize(Store::class);
        $subscriptionStore = new DummySubscriptionStore();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->remove();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertAdded(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::New,
            ),
        );
    }

    public function testRemoveWithSubscriber(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public bool $dropped = false;

            #[Teardown]
            public function drop(): void
            {
                $this->dropped = true;
            }
        };

        $subscription = new Subscription(
            $subscriptionId,
            Subscription::DEFAULT_GROUP,
            RunMode::FromBeginning,
            Status::Detached,
        );
        $subscriptionStore = new DummySubscriptionStore([$subscription]);

        $streamableStore = $this->prophesize(Store::class);

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->remove();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertNoUpdated();
        $subscriptionStore->assertRemoved($subscription);
        self::assertTrue($subscriber->dropped);
    }

    public function testRemoveWithoutDropMethod(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $subscription = new Subscription(
            $subscriptionId,
            Subscription::DEFAULT_GROUP,
            RunMode::FromBeginning,
            Status::Detached,
        );
        $subscriptionStore = new DummySubscriptionStore([$subscription]);

        $streamableStore = $this->prophesize(Store::class);

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->remove();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertNoUpdated();
        $subscriptionStore->assertRemoved($subscription);
    }

    public function testRemoveWithSubscriberAndError(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public bool $dropped = false;

            #[Teardown]
            public function drop(): void
            {
                throw new RuntimeException('ERROR');
            }
        };

        $subscription = new Subscription(
            $subscriptionId,
            Subscription::DEFAULT_GROUP,
            RunMode::FromBeginning,
            Status::Detached,
        );
        $subscriptionStore = new DummySubscriptionStore([$subscription]);

        $streamableStore = $this->prophesize(Store::class);

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->remove();

        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $subscriptionStore->assertNoUpdated();
        $subscriptionStore->assertRemoved($subscription);
    }

    public function testRemoveNewSubscriber(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public bool $dropped = false;

            #[Teardown]
            public function drop(): void
            {
                $this->dropped = true;
            }
        };

        $subscription = new Subscription(
            $subscriptionId,
            Subscription::DEFAULT_GROUP,
            RunMode::FromBeginning,
            Status::New,
        );

        $subscriptionStore = new DummySubscriptionStore([$subscription]);

        $streamableStore = $this->prophesize(Store::class);

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->remove();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertNoUpdated();
        $subscriptionStore->assertRemoved($subscription);
        self::assertFalse($subscriber->dropped);
    }

    public function testRemoveWithoutSubscriber(): void
    {
        $subscriberId = 'test';

        $subscription = new Subscription(
            $subscriberId,
            Subscription::DEFAULT_GROUP,
            RunMode::FromBeginning,
            Status::Detached,
        );
        $subscriptionStore = new DummySubscriptionStore([$subscription]);

        $streamableStore = $this->prophesize(Store::class);

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([]),
            logger: new NullLogger(),
        );

        $result = $engine->remove();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertNoUpdated();
        $subscriptionStore->assertRemoved($subscription);
    }

    public function testReactiveDiscoverNewSubscribers(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $streamableStore = $this->prophesize(Store::class);
        $subscriptionStore = new DummySubscriptionStore();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->reactivate();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertAdded(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::New,
            ),
        );
    }

    public function testReactivateError(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                0,
                new SubscriptionError('ERROR', Status::New),
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->reactivate();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::New,
                0,
            ),
        );
    }

    public function testReactivateDetached(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Detached,
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->reactivate();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        );
    }

    public function testReactivatePaused(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Paused,
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->reactivate();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        );
    }

    public function testReactivateFinished(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Finished,
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->reactivate();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        );
    }

    public function testPauseDiscoverNewSubscribers(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $streamableStore = $this->prophesize(Store::class);
        $subscriptionStore = new DummySubscriptionStore();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->pause();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertAdded(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::New,
            ),
        );
    }

    public function testPauseBooting(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->pause();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Paused,
            ),
        );
    }

    public function testPauseActive(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->pause();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Paused,
            ),
        );
    }

    public function testPauseError(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                0,
                new SubscriptionError('ERROR', Status::New),
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->pause();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
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

    public function testPauseWithoutSubscriber(): void
    {
        $subscriptionId = 'test';

        $subscriptionStore = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([]),
            logger: new NullLogger(),
        );

        $result = $engine->pause();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertUpdated(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Paused,
            ),
        );
    }

    public function testGetSubscriptionAndDiscoverNewSubscribers(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $streamableStore = $this->prophesize(Store::class);
        $subscriptionStore = new DummySubscriptionStore();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $subscriptions = $engine->subscriptions();

        $subscriptionStore->assertAdded(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::New,
            ),
        );

        self::assertCount(1, $subscriptions);
        $subscription = $subscriptions[0];

        self::assertEquals($subscriptionId, $subscription->id());
        self::assertEquals(Subscription::DEFAULT_GROUP, $subscription->group());
        self::assertEquals(RunMode::FromBeginning, $subscription->runMode());
        self::assertEquals(Status::New, $subscription->status());
    }

    public function testRetry(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function subscribe(): void
            {
                throw new RuntimeException('ERROR2');
            }
        };

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $subscription = new Subscription(
            $subscriptionId,
            Subscription::DEFAULT_GROUP,
            RunMode::FromBeginning,
            Status::Error,
            0,
            new SubscriptionError('ERROR', Status::Active),
        );

        $subscriptionStore = new DummySubscriptionStore([$subscription]);

        $retryStrategy = $this->prophesize(RetryStrategy::class);
        $retryStrategy->shouldRetry($subscription)->willReturn(true);

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            $retryStrategy->reveal(),
            new NullLogger(),
        );

        $result = $engine->run();

        self::assertEquals(1, $result->processedMessages);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR2', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        self::assertCount(2, $subscriptionStore->updatedSubscriptions);

        [$update1, $update2] = $subscriptionStore->updatedSubscriptions;

        self::assertEquals($subscriptionId, $update1->id());
        self::assertEquals(Subscription::DEFAULT_GROUP, $update1->group());
        self::assertEquals(RunMode::FromBeginning, $update1->runMode());
        self::assertEquals(Status::Active, $update1->status());
        self::assertEquals(0, $update1->position());
        self::assertNull($update1->subscriptionError());
        self::assertEquals(1, $update1->retryAttempt());

        self::assertEquals(Status::Error, $update2->status());
        self::assertEquals(Status::Active, $update2->subscriptionError()?->previousStatus);
        self::assertEquals('ERROR2', $update2->subscriptionError()?->errorMessage);
        self::assertEquals(1, $update2->retryAttempt());
    }

    #[DataProvider('statusProvider')]
    public function testShouldNotRetryOtherStatus(string $method, string $status): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $streamableStore = $this->prophesize(Store::class);

        $subscription = new Subscription(
            $subscriptionId,
            Subscription::DEFAULT_GROUP,
            RunMode::FromBeginning,
            Status::Error,
            0,
            new SubscriptionError('ERROR', Status::from($status)),
        );

        $subscriptionStore = new DummySubscriptionStore([$subscription]);

        $retryStrategy = $this->prophesize(RetryStrategy::class);
        $retryStrategy->shouldRetry($subscription)->shouldNotBeCalled();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            $retryStrategy->reveal(),
            new NullLogger(),
        );

        $result = match ($method) {
            'setup' => $engine->setup(),
            'boot' => $engine->boot(),
            'run' => $engine->run(),
        };

        self::assertCount(0, $result->errors);
        $subscriptionStore->assertNoChanges();
    }

    public static function statusProvider(): Generator
    {
        yield 'setup_booting' => ['setup', 'booting'];
        yield 'setup_active' => ['setup', 'active'];
        yield 'boot_new' => ['boot', 'new'];
        yield 'boot_active' => ['boot', 'active'];
        yield 'run_new' => ['run', 'new'];
        yield 'run_booting' => ['run', 'booting'];
    }

    public function testShouldNotRetry(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $streamableStore = $this->prophesize(Store::class);

        $subscription = new Subscription(
            $subscriptionId,
            Subscription::DEFAULT_GROUP,
            RunMode::FromBeginning,
            Status::Error,
            0,
            new SubscriptionError('ERROR', Status::Active),
        );

        $subscriptionStore = new DummySubscriptionStore([$subscription]);

        $retryStrategy = $this->prophesize(RetryStrategy::class);
        $retryStrategy->shouldRetry($subscription)->willReturn(false);

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            $retryStrategy->reveal(),
            new NullLogger(),
        );

        $result = $engine->run();

        self::assertEquals(0, $result->processedMessages);
        self::assertCount(0, $result->errors);

        $subscriptionStore->assertNoChanges();
    }

    #[DataProvider('methodProvider')]
    public function testCriteria(string $method): void
    {
        $subscriber = new #[Subscriber('id1', RunMode::FromBeginning)]
        class {
        };

        $subscriptionStore = $this->prophesize(SubscriptionStore::class);
        $subscriptionStore->find(
            Argument::that(
                static fn (SubscriptionCriteria $criteria,
                ) => $criteria->ids === ['id1'] && $criteria->groups === ['group1'],
            ),
        )->willReturn([])->shouldBeCalled();

        $subscriptionStore->find(
            new SubscriptionCriteria(),
        )->willReturn([
            new Subscription('id1'),
        ])->shouldBeCalled();

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([]));

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore->reveal(),
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $engineCriteria = new SubscriptionEngineCriteria(
            ids: ['id1'],
            groups: ['group1'],
        );

        $engine->{$method}($engineCriteria);
    }

    #[DataProvider('methodProvider')]
    public function testWithLockableStore(string $method): void
    {
        $subscriber = new #[Subscriber('id1', RunMode::FromNow)]
        class {
        };

        $subscriptionStore = $this->prophesize(LockableSubscriptionStore::class);
        $subscriptionStore->inLock(Argument::type(Closure::class))->will(
        /** @param array{Closure} $args */
            static fn (array $args): mixed => $args[0](),
        )->shouldBeCalled();
        $subscriptionStore->find(Argument::any())->willReturn([])->shouldBeCalled();

        $subscriptionStore->find(
            new SubscriptionCriteria(),
        )->willReturn([
            new Subscription('id1'),
        ])->shouldBeCalled();

        $subscriptionStore->remove(Argument::type(Subscription::class));
        $subscriptionStore->add(Argument::type(Subscription::class));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([]));

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore->reveal(),
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $engine->{$method}();
    }

    public function testDontLockGetSubscriptions(): void
    {
        $subscriber = new #[Subscriber('id1', RunMode::FromNow)]
        class {
        };

        $subscriptionStore = $this->prophesize(LockableSubscriptionStore::class);
        $subscriptionStore->inLock(Argument::type(Closure::class))->will(
        /** @param array{Closure} $args */
            static fn (array $args): mixed => $args[0](),
        )->shouldNotBeCalled();
        $subscriptionStore->find(Argument::any())->willReturn([])->shouldBeCalled();

        $subscriptionStore->find(
            new SubscriptionCriteria(),
        )->willReturn([
            new Subscription('id1'),
        ])->shouldBeCalled();

        $subscriptionStore->remove(Argument::type(Subscription::class));
        $subscriptionStore->add(Argument::type(Subscription::class));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([]));

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore->reveal(),
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $engine->subscriptions();
    }

    public function testFromNowWithoutSetupDirectActive(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromNow)]
        class {
        };

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load(null, 1, null, true)->willReturn(new ArrayStream([$message1]))->shouldBeCalledOnce();

        $subscriptionStore = new DummySubscriptionStore();

        $engine = new DefaultSubscriptionEngine(
            $streamableStore->reveal(),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            logger: new NullLogger(),
        );

        $result = $engine->setup();

        self::assertEquals([], $result->errors);

        $subscriptionStore->assertAdded(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromNow,
                Status::Active,
                1,
            ),
        );
    }

    public static function methodProvider(): Generator
    {
        yield 'setup' => ['setup'];
        yield 'boot' => ['boot'];
        yield 'run' => ['run'];
        yield 'teardown' => ['teardown'];
        yield 'remove' => ['remove'];
        yield 'reactivate' => ['reactivate'];
    }

    private function criteria(int $fromIndex = 0): Criteria
    {
        return new Criteria(new FromIndexCriterion($fromIndex));
    }
}
