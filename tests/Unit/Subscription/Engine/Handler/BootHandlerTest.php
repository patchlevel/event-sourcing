<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Attribute\OnFailed;
use Patchlevel\EventSourcing\Attribute\RetryStrategy as RetryStrategyName;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot as BootCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Handler\BootHandler;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\FailSubscriber;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\RetrySubscriber;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\MessageProcessor;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ClockBasedRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\NoRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
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
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(BootHandler::class)]
final class BootHandlerTest extends TestCase
{
    /** @param iterable<object> $subscribers */
    private function createHandler(
        MessageLoader $messageLoader,
        DummySubscriptionStore $store,
        array $subscribers = [],
        RetryStrategyRepository|null $retryStrategyRepository = null,
    ): BootHandler {
        $retryStrategyRepository ??= new RetryStrategyRepository([
            RetryStrategyRepository::DEFAULT_STRATEGY_NAME => new ClockBasedRetryStrategy(),
            'no_retry' => new NoRetryStrategy(),
        ]);

        $subscriberRepository = new MetadataSubscriberAccessorRepository($subscribers);
        $subscriptionManager = new SubscriptionManager($store);
        $eventDispatcher = new EventDispatcher();

        $eventDispatcher->addSubscriber(new RetrySubscriber($subscriptionManager, $subscriberRepository, $retryStrategyRepository, new NullLogger()));
        $eventDispatcher->addSubscriber(new FailSubscriber($subscriptionManager, $subscriberRepository, new NullLogger()));

        $messageProcessor = new MessageProcessor($subscriberRepository, $eventDispatcher, new NullLogger());

        return new BootHandler($messageLoader, $subscriptionManager, $subscriberRepository, $messageProcessor, $eventDispatcher, new NullLogger());
    }

    public function testNothingToBoot(): void
    {
        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->never())->method('load');

        $store = new DummySubscriptionStore();
        $handler = $this->createHandler($messageLoader, $store);

        $result = $handler(new BootCommand());

        self::assertEquals(0, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $store->assertNoChanges();
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

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Booting),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        $handler = $this->createHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new BootCommand());

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $store->assertNoAdded();
        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active, 1),
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

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Booting),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        $handler = $this->createHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new BootCommand());

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
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
                new SubscriptionError('ERROR', Status::Booting, ThrowableToErrorContextTransformer::transform($subscriber->exception)),
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

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Booting),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        $handler = $this->createHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new BootCommand());

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
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
                new SubscriptionError('ERROR', Status::Booting, ThrowableToErrorContextTransformer::transform($subscriber->exception)),
            ),
        );
    }

    public function testBootWithErrorAndRecovery(): void
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

            #[OnFailed]
            public function onFailed(): void
            {
            }
        };

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Booting),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        $handler = $this->createHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new BootCommand());

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];
        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Booting, 1),
        );
    }

    public function testBootWithErrorAndRecoveryFailed(): void
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

            #[OnFailed]
            public function onFailed(): void
            {
                throw new RuntimeException('RECOVERY ERROR');
            }
        };

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Booting),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        $handler = $this->createHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new BootCommand());

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
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
                new SubscriptionError('ERROR', Status::Booting, ThrowableToErrorContextTransformer::transform($subscriber->exception)),
            ),
        );
    }

    public function testBootWithErrorAndRecoveryFailedBecauseBatching(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        #[RetryStrategyName('no_retry')]
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

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Booting),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        $handler = $this->createHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new BootCommand());

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
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
                new SubscriptionError('ERROR', Status::Booting, ThrowableToErrorContextTransformer::transform($subscriber->exception)),
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

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Booting),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        $handler = $this->createHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new BootCommand(limit: 1));

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(false, $result->finished);
        self::assertEquals([], $result->errors);

        $store->assertNoAdded();
        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Booting, 1),
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

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId1, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Booting),
            new Subscription($subscriptionId2, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Booting, 1),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        $handler = $this->createHandler($messageLoader, $store, [$subscriber1, $subscriber2]);
        $result = $handler(new BootCommand());

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription($subscriptionId1, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active, 1),
            new Subscription($subscriptionId2, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active, 1),
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

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Booting),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));
        $message2 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([
            1 => $message1,
            3 => $message2,
        ]));

        $handler = $this->createHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new BootCommand());

        self::assertEquals(2, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active, 3),
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

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::Once, Status::Booting),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        $handler = $this->createHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new BootCommand());

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::Once, Status::Finished, 1),
        );

        self::assertEquals($message, $subscriber->message);
    }

    public function testBootWithoutSubscriber(): void
    {
        $subscriptionId = 'test';

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Booting),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->willReturn(new Stream([1 => $message]));

        $handler = $this->createHandler($messageLoader, $store);
        $result = $handler(new BootCommand());

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $store->assertNoChanges();
    }
}
