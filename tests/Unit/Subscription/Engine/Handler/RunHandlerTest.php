<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Attribute\OnFailed;
use Patchlevel\EventSourcing\Attribute\RetryStrategy as RetryStrategyName;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run as RunCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Handler\RunHandler;
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
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Tests\Unit\Subscription\DummySubscriptionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(RunHandler::class)]
final class RunHandlerTest extends TestCase
{
    /** @param iterable<object> $subscribers */
    private function createHandler(
        MessageLoader $messageLoader,
        DummySubscriptionStore $store,
        array $subscribers = [],
        RetryStrategyRepository|null $retryStrategyRepository = null,
    ): array {
        $retryStrategyRepository ??= new RetryStrategyRepository([
            RetryStrategyRepository::DEFAULT_STRATEGY_NAME => new ClockBasedRetryStrategy(),
            'no_retry' => new NoRetryStrategy(),
        ]);

        $subscriberRepository = new MetadataSubscriberAccessorRepository($subscribers);
        $subscriptionManager = new SubscriptionManager($store);
        $eventDispatcher = new EventDispatcher();

        $eventDispatcher->addSubscriber(new RetrySubscriber($subscriptionManager, $subscriberRepository, $retryStrategyRepository, new NullLogger()));
        $eventDispatcher->addSubscriber(new FailSubscriber($subscriptionManager, $subscriberRepository, new NullLogger()));
        $eventDispatcher->addListener(OnCommand::class, new DetachListener($subscriptionManager, $subscriberRepository, new NullLogger()), 32);

        $messageProcessor = new MessageProcessor($subscriberRepository, $eventDispatcher, new NullLogger());

        $handler = new RunHandler($messageLoader, $subscriptionManager, $messageProcessor, $eventDispatcher, new NullLogger());

        return [$handler, $eventDispatcher, new RunCommand()];
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

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        [$handler, $eventDispatcher, $command] = $this->createHandler($messageLoader, $store, [$subscriber]);
        $eventDispatcher->dispatch(new OnCommand($command));
        $result = $handler($command);

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active, 1),
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

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));
        $message2 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)
            ->willReturn(new Stream([1 => $message1, 2 => $message2]));

        $command = new RunCommand(limit: 1);
        [$handler, $eventDispatcher] = $this->createHandler($messageLoader, $store, [$subscriber]);
        $eventDispatcher->dispatch(new OnCommand($command));
        $result = $handler($command);

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(false, $result->finished);
        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active, 1),
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

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId1, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active),
            new Subscription($subscriptionId2, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active, 1),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        [$handler, $eventDispatcher, $command] = $this->createHandler($messageLoader, $store, [$subscriber1, $subscriber2]);
        $eventDispatcher->dispatch(new OnCommand($command));
        $result = $handler($command);

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

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        [$handler, $eventDispatcher, $command] = $this->createHandler($messageLoader, $store, [$subscriber]);
        $eventDispatcher->dispatch(new OnCommand($command));
        $result = $handler($command);

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
                new SubscriptionError('ERROR', Status::Active, ThrowableToErrorContextTransformer::transform($subscriber->exception)),
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

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        [$handler, $eventDispatcher, $command] = $this->createHandler($messageLoader, $store, [$subscriber]);
        $eventDispatcher->dispatch(new OnCommand($command));
        $result = $handler($command);

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
                new SubscriptionError('ERROR', Status::Active, ThrowableToErrorContextTransformer::transform($subscriber->exception)),
            ),
        );
    }

    public function testRunningWithErrorAndRecovery(): void
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
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        [$handler, $eventDispatcher, $command] = $this->createHandler($messageLoader, $store, [$subscriber]);
        $eventDispatcher->dispatch(new OnCommand($command));
        $result = $handler($command);

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];
        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active, 1),
        );
    }

    public function testRunningWithErrorAndRecoveryFailed(): void
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
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        [$handler, $eventDispatcher, $command] = $this->createHandler($messageLoader, $store, [$subscriber]);
        $eventDispatcher->dispatch(new OnCommand($command));
        $result = $handler($command);

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
                new SubscriptionError('ERROR', Status::Active, ThrowableToErrorContextTransformer::transform($subscriber->exception)),
            ),
        );
    }

    public function testRunningMarkDetached(): void
    {
        $subscriptionId = 'test';

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active),
        ]);

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->never())->method('load');

        // DetachListener fires on OnCommand and marks the subscription as Detached
        // before the handler processes it (no subscriber registered)
        [$handler, $eventDispatcher, $command] = $this->createHandler($messageLoader, $store);
        $eventDispatcher->dispatch(new OnCommand($command));
        $result = $handler($command);

        self::assertEquals(0, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Detached, 0),
        );
    }

    public function testRunningWithoutActiveSubscribers(): void
    {
        $subscriptionId = 'test';

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Booting),
        ]);

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->never())->method('load');

        [$handler, $eventDispatcher, $command] = $this->createHandler($messageLoader, $store);
        $eventDispatcher->dispatch(new OnCommand($command));
        $result = $handler($command);

        self::assertEquals(0, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $store->assertNoChanges();
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

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));
        $message2 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([
            1 => $message1,
            3 => $message2,
        ]));

        [$handler, $eventDispatcher, $command] = $this->createHandler($messageLoader, $store, [$subscriber]);
        $eventDispatcher->dispatch(new OnCommand($command));
        $result = $handler($command);

        self::assertEquals(2, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active, 3),
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

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::Once, Status::Active),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $messageLoader = $this->createMock(MessageLoader::class);
        $messageLoader->expects($this->once())->method('load')->with(null)->willReturn(new Stream([1 => $message]));

        [$handler, $eventDispatcher, $command] = $this->createHandler($messageLoader, $store, [$subscriber]);
        $eventDispatcher->dispatch(new OnCommand($command));
        $result = $handler($command);

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals(true, $result->finished);
        self::assertEquals([], $result->errors);

        $store->assertUpdated(
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::Once, Status::Finished, 1),
        );

        self::assertEquals($message, $subscriber->message);
    }
}
