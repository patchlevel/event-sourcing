<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Attribute\Partition;
use Patchlevel\EventSourcing\Attribute\PartitionKey;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnBeforeHandleMessage;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessage;
use Patchlevel\EventSourcing\Subscription\Engine\MessageProcessor;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessingStopped;
use Patchlevel\EventSourcing\Subscription\Partitioner\StreamPartitioner;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscriber\NoSuitableResolver;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(MessageProcessor::class)]
final class MessageProcessorTest extends TestCase
{
    public function testProcessInvokesSubscribeMethod(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public ProfileVisited|null $event = null;

            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(ProfileVisited $event): void
            {
                $this->event = $event;
            }
        };

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new EventDispatcher(),
            [],
            new NullLogger(),
        );

        $event = new ProfileVisited(ProfileId::fromString('1'));
        $message = Message::create($event);
        $subscription = new Subscription('test', status: Status::Active);

        $error = $processor->process(1, $message, $subscription);

        self::assertNull($error);
        self::assertSame($event, $subscriber->event);
        self::assertSame(1, $subscription->position());
    }

    public function testProcessResolvesMessageArgument(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(Message $message): void
            {
                $this->message = $message;
            }
        };

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new EventDispatcher(),
            [],
            new NullLogger(),
        );

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));
        $subscription = new Subscription('test', status: Status::Active);

        $error = $processor->process(1, $message, $subscription);

        self::assertNull($error);
        self::assertSame($message, $subscriber->message);
    }

    public function testProcessResolvesMultipleArguments(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public ProfileVisited|null $event = null;
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(ProfileVisited $event, Message $message): void
            {
                $this->event = $event;
                $this->message = $message;
            }
        };

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new EventDispatcher(),
            [],
            new NullLogger(),
        );

        $event = new ProfileVisited(ProfileId::fromString('1'));
        $message = Message::create($event);
        $subscription = new Subscription('test', status: Status::Active);

        $error = $processor->process(1, $message, $subscription);

        self::assertNull($error);
        self::assertSame($event, $subscriber->event);
        self::assertSame($message, $subscriber->message);
    }

    public function testProcessReturnsErrorWhenArgumentCannotBeResolved(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(ProfileVisited $event, int $unresolved): void
            {
            }
        };

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new EventDispatcher(),
            [],
            new NullLogger(),
        );

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));
        $subscription = new Subscription('test', status: Status::Active);

        $error = $processor->process(1, $message, $subscription);

        self::assertInstanceOf(Error::class, $error);
        self::assertInstanceOf(NoSuitableResolver::class, $error->throwable);
    }

    public function testListenerCanStopTheSubscription(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public bool $called = false;

            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(ProfileVisited $event): void
            {
                $this->called = true;
            }
        };

        $handled = 0;
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(OnBeforeHandleMessage::class, static function (OnBeforeHandleMessage $event): void {
            $event->stop = true;
        });
        $eventDispatcher->addListener(OnHandleMessage::class, static function () use (&$handled): void {
            $handled++;
        });

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            $eventDispatcher,
            [],
            new NullLogger(),
        );

        $subscription = new Subscription('test', status: Status::Active, position: 0);

        $error = $processor->process(1, Message::create(new ProfileVisited(ProfileId::fromString('1'))), $subscription);

        self::assertInstanceOf(ProcessingStopped::class, $error);
        self::assertFalse($subscriber->called);
        self::assertSame(0, $handled, 'OnHandleMessage is not dispatched, it would have no success or error');
        self::assertSame(0, $subscription->position(), 'a stopped subscription does not move the position');
    }

    public function testPartitionGetsItsPartitionKey(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        #[Partition(new StreamPartitioner())]
        class {
            public string|null $partitionKey = null;

            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(ProfileVisited $event, #[PartitionKey]
            string $partitionKey,): void
            {
                $this->partitionKey = $partitionKey;
            }
        };

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new EventDispatcher(),
            [],
            new NullLogger(),
        );

        $partition = Subscription::partition(new Subscription('test', status: Status::Active), 'tenant-a', 0);

        $error = $processor->process(1, Message::create(new ProfileVisited(ProfileId::fromString('1'))), $partition);

        self::assertNull($error);
        self::assertSame('tenant-a', $subscriber->partitionKey);
    }
}
