<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Listener;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Attribute\BatchBegin;
use Patchlevel\EventSourcing\Attribute\BatchFlush;
use Patchlevel\EventSourcing\Attribute\BatchState;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot as BootCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run as RunCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Handler\BootHandler;
use Patchlevel\EventSourcing\Subscription\Engine\Handler\RunHandler;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\BatchSubscriber;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\DetachListener;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\FailSubscriber;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\RetrySubscriber;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\MessageProcessor;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionRunner;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ClockBasedRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\NoRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\BatchArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\BatchManager;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use Patchlevel\EventSourcing\Subscription\ThrowableToErrorContextTransformer;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\AfterMessagesBatchingSubscriber;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\BatchingSubscriber;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\DefaultStateBatchingSubscriber;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\VoidBeginBatchingSubscriber;
use Patchlevel\EventSourcing\Tests\Unit\Subscription\DummySubscriptionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(BatchSubscriber::class)]
final class BatchSubscriberTest extends TestCase
{
    /** @param list<object> $subscribers */
    private function createBootHandler(
        MessageLoader $messageLoader,
        DummySubscriptionStore $store,
        array $subscribers,
    ): BootHandler {
        $retryStrategyRepository = new RetryStrategyRepository([
            RetryStrategyRepository::DEFAULT_STRATEGY_NAME => new ClockBasedRetryStrategy(),
            'no_retry' => new NoRetryStrategy(),
        ]);

        $subscriberRepository = new MetadataSubscriberAccessorRepository($subscribers);
        $subscriptionManager = new SubscriptionManager($store);
        $eventDispatcher = new EventDispatcher();
        $batchManager = new BatchManager();

        $eventDispatcher->addSubscriber(new BatchSubscriber($batchManager, $subscriberRepository, new NullLogger()));
        $eventDispatcher->addSubscriber(new RetrySubscriber($subscriptionManager, $subscriberRepository, $retryStrategyRepository, new NullLogger()));
        $eventDispatcher->addSubscriber(new FailSubscriber($subscriptionManager, $subscriberRepository, new NullLogger()));

        $messageProcessor = new MessageProcessor($subscriberRepository, $eventDispatcher, [new BatchArgumentResolver($batchManager)], new NullLogger());

        $runner = new SubscriptionRunner($messageLoader, $subscriptionManager, $subscriberRepository, $messageProcessor, $eventDispatcher, new NullLogger());

        return new BootHandler($subscriptionManager, $runner);
    }

    /**
     * @param list<object> $subscribers
     *
     * @return array{RunHandler, EventDispatcher, RunCommand}
     */
    private function createRunHandler(
        MessageLoader $messageLoader,
        DummySubscriptionStore $store,
        array $subscribers,
    ): array {
        $retryStrategyRepository = new RetryStrategyRepository([
            RetryStrategyRepository::DEFAULT_STRATEGY_NAME => new ClockBasedRetryStrategy(),
            'no_retry' => new NoRetryStrategy(),
        ]);

        $subscriberRepository = new MetadataSubscriberAccessorRepository($subscribers);
        $subscriptionManager = new SubscriptionManager($store);
        $eventDispatcher = new EventDispatcher();
        $batchManager = new BatchManager();

        $eventDispatcher->addSubscriber(new BatchSubscriber($batchManager, $subscriberRepository, new NullLogger()));
        $eventDispatcher->addSubscriber(new RetrySubscriber($subscriptionManager, $subscriberRepository, $retryStrategyRepository, new NullLogger()));
        $eventDispatcher->addSubscriber(new FailSubscriber($subscriptionManager, $subscriberRepository, new NullLogger()));
        $eventDispatcher->addListener(OnCommand::class, new DetachListener($subscriptionManager, $subscriberRepository, new NullLogger()), 32);

        $messageProcessor = new MessageProcessor($subscriberRepository, $eventDispatcher, [new BatchArgumentResolver($batchManager)], new NullLogger());

        $runner = new SubscriptionRunner($messageLoader, $subscriptionManager, $subscriberRepository, $messageProcessor, $eventDispatcher, new NullLogger());

        $handler = new RunHandler($subscriptionManager, $runner);

        return [$handler, $eventDispatcher, new RunCommand()];
    }

    public function testBootBatchingSuccess(): void
    {
        $subscriber = new BatchingSubscriber();

        $store = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        $handler = $this->createBootHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new BootCommand());

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $store->assertNoAdded();
        $store->assertUpdated(
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
        self::assertSame(1, $subscriber->flushCalled);
        self::assertSame(0, $subscriber->rollbackCalled);
    }

    public function testBootBatchingWithDefaultState(): void
    {
        $subscriber = new DefaultStateBatchingSubscriber();

        $store = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        $handler = $this->createBootHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new BootCommand());

        self::assertEquals([], $result->errors);

        self::assertInstanceOf(stdClass::class, $subscriber->receivedState);
        self::assertSame($subscriber->receivedState, $subscriber->flushedState);
    }

    public function testBootBatchingWithVoidBeginUsesDefaultState(): void
    {
        $subscriber = new VoidBeginBatchingSubscriber();

        $store = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        $handler = $this->createBootHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new BootCommand());

        self::assertEquals([], $result->errors);

        self::assertTrue($subscriber->beginCalled);
        self::assertInstanceOf(stdClass::class, $subscriber->receivedState);
    }

    public function testBootBatchingSuccessForceCommit(): void
    {
        $subscriber = new BatchingSubscriber(
            flushAfterMessages: 1,
        );

        $store = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));
        $message2 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([
            1 => $message1,
            2 => $message2,
        ]));

        $handler = $this->createBootHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new BootCommand());

        self::assertEquals(2, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $store->assertNoAdded();
        $store->assertUpdated(
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
        self::assertSame(2, $subscriber->flushCalled);
        self::assertSame(0, $subscriber->rollbackCalled);
    }

    public function testBootBatchingWithHandleError(): void
    {
        $exception = new RuntimeException('ERROR');

        $subscriber = new BatchingSubscriber(
            throwForMessage: $exception,
        );

        $store = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        $handler = $this->createBootHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new BootCommand());

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);

        $error = $result->errors[0];
        self::assertEquals($subscriber::ID, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $store->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                null,
                new SubscriptionError(
                    'ERROR',
                    Status::Booting,
                    ThrowableToErrorContextTransformer::transform($exception),
                ),
            ),
        );

        self::assertSame([$message], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(0, $subscriber->flushCalled);
        self::assertSame(1, $subscriber->rollbackCalled);
    }

    public function testBootBatchingWithBeginBatchError(): void
    {
        $exception = new RuntimeException('ERROR');

        $subscriber = new BatchingSubscriber(
            throwForBeginBatch: $exception,
        );

        $store = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        $handler = $this->createBootHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new BootCommand());

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);

        $error = $result->errors[0];
        self::assertEquals($subscriber::ID, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $store->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                null,
                new SubscriptionError(
                    'ERROR',
                    Status::Booting,
                    ThrowableToErrorContextTransformer::transform($exception),
                ),
            ),
        );

        self::assertSame([], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(0, $subscriber->flushCalled);
        self::assertSame(0, $subscriber->rollbackCalled);
    }

    public function testBootBatchingWithCommitBatchError(): void
    {
        $exception = new RuntimeException('ERROR');

        $subscriber = new BatchingSubscriber(
            throwForFlush: $exception,
        );

        $store = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        $handler = $this->createBootHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new BootCommand());

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);

        $error = $result->errors[0];
        self::assertEquals($subscriber::ID, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $store->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                null,
                new SubscriptionError(
                    'ERROR',
                    Status::Booting,
                    ThrowableToErrorContextTransformer::transform($exception),
                ),
            ),
        );

        self::assertSame([$message], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(1, $subscriber->flushCalled);
        self::assertSame(0, $subscriber->rollbackCalled);
    }

    public function testBootBatchingWithRollbackBatchError(): void
    {
        $exception = new RuntimeException('ERROR');

        $subscriber = new BatchingSubscriber(
            throwForMessage: $exception,
            throwForRollback: new RuntimeException('ERROR'),
        );

        $store = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        $handler = $this->createBootHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new BootCommand());

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);

        $error = $result->errors[0];
        self::assertEquals($subscriber::ID, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $store->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                null,
                new SubscriptionError(
                    'ERROR',
                    Status::Booting,
                    ThrowableToErrorContextTransformer::transform($exception),
                ),
            ),
        );

        self::assertSame([$message], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(0, $subscriber->flushCalled);
        self::assertSame(1, $subscriber->rollbackCalled);
    }

    public function testBootBatchingFlushesAfterMessageThreshold(): void
    {
        $subscriber = new AfterMessagesBatchingSubscriber();

        $store = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));
        $message2 = new Message(new ProfileVisited(ProfileId::fromString('test')));
        $message3 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([
            1 => $message1,
            2 => $message2,
            3 => $message3,
        ]));

        $handler = $this->createBootHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new BootCommand());

        self::assertEquals(3, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        // first batch flushes once the threshold of 2 is reached, the third
        // message opens a new batch that is flushed when processing finishes
        self::assertSame([$message1, $message2, $message3], $subscriber->receivedMessages);
        self::assertSame(2, $subscriber->beginBatchCalled);
        self::assertSame(2, $subscriber->flushCalled);
        self::assertSame([2, 1], $subscriber->flushedBatchSizes);
    }

    public function testBootBatchingWithNonObjectBeginStateFails(): void
    {
        $subscriber = new #[Subscriber('non-object', RunMode::FromBeginning)]
        class {
            #[BatchBegin]
            public function begin(): string
            {
                return 'not-an-object';
            }

            #[Subscribe(ProfileVisited::class)]
            public function handle(
                Message $message,
                #[BatchState]
                object $state,
            ): void {
            }

            #[BatchFlush]
            public function flush(object $state): void
            {
            }
        };

        $store = new DummySubscriptionStore([
            new Subscription(
                'non-object',
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Booting,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        $handler = $this->createBootHandler($messageLoader, $store, [$subscriber]);
        $result = $handler(new BootCommand());

        $error = $result->errors[0];
        self::assertEquals('non-object', $error->subscriptionId);
        self::assertStringContainsString('begin batch method must return an object or null', $error->message);
        self::assertInstanceOf(InvalidArgumentException::class, $error->throwable);
    }

    public function testRunningBatchingSuccess(): void
    {
        $subscriber = new BatchingSubscriber();

        $store = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        [$handler, $eventDispatcher, $command] = $this->createRunHandler($messageLoader, $store, [$subscriber]);
        $eventDispatcher->dispatch(new OnCommand($command));
        $result = $handler($command);

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $store->assertNoAdded();
        $store->assertUpdated(
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
        self::assertSame(1, $subscriber->flushCalled);
        self::assertSame(0, $subscriber->rollbackCalled);
    }

    public function testRunningBatchingSuccessForceCommit(): void
    {
        $subscriber = new BatchingSubscriber(
            flushAfterMessages: 1,
        );

        $store = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));
        $message2 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([
            1 => $message1,
            2 => $message2,
        ]));

        [$handler, $eventDispatcher, $command] = $this->createRunHandler($messageLoader, $store, [$subscriber]);
        $eventDispatcher->dispatch(new OnCommand($command));
        $result = $handler($command);

        self::assertEquals(2, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $store->assertNoAdded();
        $store->assertUpdated(
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
        self::assertSame(2, $subscriber->flushCalled);
        self::assertSame(0, $subscriber->rollbackCalled);
    }

    public function testRunningBatchingWithHandleError(): void
    {
        $exception = new RuntimeException('ERROR');

        $subscriber = new BatchingSubscriber(
            throwForMessage: $exception,
        );

        $store = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        [$handler, $eventDispatcher, $command] = $this->createRunHandler($messageLoader, $store, [$subscriber]);
        $eventDispatcher->dispatch(new OnCommand($command));
        $result = $handler($command);

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);

        $error = $result->errors[0];
        self::assertEquals($subscriber::ID, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $store->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                null,
                new SubscriptionError(
                    'ERROR',
                    Status::Active,
                    ThrowableToErrorContextTransformer::transform($exception),
                ),
            ),
        );

        self::assertSame([$message], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(0, $subscriber->flushCalled);
        self::assertSame(1, $subscriber->rollbackCalled);
    }

    public function testRunningBatchingWithBeginBatchError(): void
    {
        $exception = new RuntimeException('ERROR');

        $subscriber = new BatchingSubscriber(
            throwForBeginBatch: $exception,
        );

        $store = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        [$handler, $eventDispatcher, $command] = $this->createRunHandler($messageLoader, $store, [$subscriber]);
        $eventDispatcher->dispatch(new OnCommand($command));
        $result = $handler($command);

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);

        $error = $result->errors[0];
        self::assertEquals($subscriber::ID, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $store->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                null,
                new SubscriptionError(
                    'ERROR',
                    Status::Active,
                    ThrowableToErrorContextTransformer::transform($exception),
                ),
            ),
        );

        self::assertSame([], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(0, $subscriber->flushCalled);
        self::assertSame(0, $subscriber->rollbackCalled);
    }

    public function testRunningBatchingWithCommitBatchError(): void
    {
        $exception = new RuntimeException('ERROR');

        $subscriber = new BatchingSubscriber(
            throwForFlush: $exception,
        );

        $store = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        [$handler, $eventDispatcher, $command] = $this->createRunHandler($messageLoader, $store, [$subscriber]);
        $eventDispatcher->dispatch(new OnCommand($command));
        $result = $handler($command);

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);

        $error = $result->errors[0];
        self::assertEquals($subscriber::ID, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $store->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                null,
                new SubscriptionError(
                    'ERROR',
                    Status::Active,
                    ThrowableToErrorContextTransformer::transform($exception),
                ),
            ),
        );

        self::assertSame([$message], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(1, $subscriber->flushCalled);
        self::assertSame(0, $subscriber->rollbackCalled);
    }

    public function testRunningBatchingWithRollbackBatchError(): void
    {
        $exception = new RuntimeException('ERROR');

        $subscriber = new BatchingSubscriber(
            throwForMessage: $exception,
            throwForRollback: new RuntimeException('ERROR'),
        );

        $store = new DummySubscriptionStore([
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Active,
            ),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        [$handler, $eventDispatcher, $command] = $this->createRunHandler($messageLoader, $store, [$subscriber]);
        $eventDispatcher->dispatch(new OnCommand($command));
        $result = $handler($command);

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);

        $error = $result->errors[0];
        self::assertEquals($subscriber::ID, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $store->assertUpdated(
            new Subscription(
                $subscriber::ID,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Error,
                null,
                new SubscriptionError(
                    'ERROR',
                    Status::Active,
                    ThrowableToErrorContextTransformer::transform($exception),
                ),
            ),
        );

        self::assertSame([$message], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(0, $subscriber->flushCalled);
        self::assertSame(1, $subscriber->rollbackCalled);
    }
}
