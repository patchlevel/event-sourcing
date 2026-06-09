<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Listener;

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
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ClockBasedRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\NoRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use Patchlevel\EventSourcing\Subscription\ThrowableToErrorContextTransformer;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\BatchingSubscriber;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Tests\Unit\Subscription\DummySubscriptionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(BatchSubscriber::class)]
final class BatchSubscriberTest extends TestCase
{
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

        $eventDispatcher->addSubscriber(new BatchSubscriber($subscriberRepository, new NullLogger()));
        $eventDispatcher->addSubscriber(new RetrySubscriber($subscriptionManager, $subscriberRepository, $retryStrategyRepository, new NullLogger()));
        $eventDispatcher->addSubscriber(new FailSubscriber($subscriptionManager, $subscriberRepository, new NullLogger()));

        $messageProcessor = new MessageProcessor($subscriberRepository, $eventDispatcher, new NullLogger());

        return new BootHandler($messageLoader, $subscriptionManager, $subscriberRepository, $messageProcessor, $eventDispatcher, new NullLogger());
    }

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

        $eventDispatcher->addSubscriber(new BatchSubscriber($subscriberRepository, new NullLogger()));
        $eventDispatcher->addSubscriber(new RetrySubscriber($subscriptionManager, $subscriberRepository, $retryStrategyRepository, new NullLogger()));
        $eventDispatcher->addSubscriber(new FailSubscriber($subscriptionManager, $subscriberRepository, new NullLogger()));
        $eventDispatcher->addListener(OnCommand::class, new DetachListener($subscriptionManager, $subscriberRepository, new NullLogger()), 32);

        $messageProcessor = new MessageProcessor($subscriberRepository, $eventDispatcher, new NullLogger());

        $handler = new RunHandler($messageLoader, $subscriptionManager, $messageProcessor, $eventDispatcher, new NullLogger());

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
        self::assertSame(1, $subscriber->commitBatchCalled);
        self::assertSame(0, $subscriber->rollbackBatchCalled);
    }

    public function testBootBatchingSuccessForceCommit(): void
    {
        $subscriber = new BatchingSubscriber(
            forceCommitAfterMessages: 1,
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
        self::assertSame(2, $subscriber->commitBatchCalled);
        self::assertSame(0, $subscriber->rollbackBatchCalled);
    }

    public function testBootBatchingWithHandleError(): void
    {
        $subscriber = new BatchingSubscriber(
            throwForMessage: new \RuntimeException('ERROR'),
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
        self::assertInstanceOf(\RuntimeException::class, $error->throwable);

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
            throwForBeginBatch: new \RuntimeException('ERROR'),
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
        self::assertInstanceOf(\RuntimeException::class, $error->throwable);

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
            throwForCommitBatch: new \RuntimeException('ERROR'),
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
        self::assertInstanceOf(\RuntimeException::class, $error->throwable);

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
            throwForMessage: new \RuntimeException('ERROR'),
            throwForRollbackBatch: new \RuntimeException('ERROR'),
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
        self::assertInstanceOf(\RuntimeException::class, $error->throwable);

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
                    ThrowableToErrorContextTransformer::transform($subscriber->throwForMessage),
                ),
            ),
        );

        self::assertSame([$message], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(0, $subscriber->commitBatchCalled);
        self::assertSame(1, $subscriber->rollbackBatchCalled);
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
        self::assertSame(1, $subscriber->commitBatchCalled);
        self::assertSame(0, $subscriber->rollbackBatchCalled);
    }

    public function testRunningBatchingSuccessForceCommit(): void
    {
        $subscriber = new BatchingSubscriber(
            forceCommitAfterMessages: 1,
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
        self::assertSame(2, $subscriber->commitBatchCalled);
        self::assertSame(0, $subscriber->rollbackBatchCalled);
    }

    public function testRunningBatchingWithHandleError(): void
    {
        $subscriber = new BatchingSubscriber(
            throwForMessage: new \RuntimeException('ERROR'),
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
        self::assertInstanceOf(\RuntimeException::class, $error->throwable);

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
            throwForBeginBatch: new \RuntimeException('ERROR'),
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
        self::assertInstanceOf(\RuntimeException::class, $error->throwable);

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
            throwForCommitBatch: new \RuntimeException('ERROR'),
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
        self::assertInstanceOf(\RuntimeException::class, $error->throwable);

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
            throwForMessage: new \RuntimeException('ERROR'),
            throwForRollbackBatch: new \RuntimeException('ERROR'),
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
        self::assertInstanceOf(\RuntimeException::class, $error->throwable);

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
                    ThrowableToErrorContextTransformer::transform($subscriber->throwForMessage),
                ),
            ),
        );

        self::assertSame([$message], $subscriber->receivedMessages);
        self::assertSame(1, $subscriber->beginBatchCalled);
        self::assertSame(0, $subscriber->commitBatchCalled);
        self::assertSame(1, $subscriber->rollbackBatchCalled);
    }
}
