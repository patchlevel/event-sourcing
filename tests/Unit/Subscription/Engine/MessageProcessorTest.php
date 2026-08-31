<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageError;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageSuccess;
use Patchlevel\EventSourcing\Subscription\Engine\MessageProcessor;
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
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(MessageProcessor::class)]
final class MessageProcessorTest extends TestCase
{
    public function testFailingTerminalListenerIsReportedAsError(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(ProfileVisited $event): void
            {
            }
        };

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            OnHandleMessageSuccess::class,
            static fn (): never => throw new RuntimeException('FLUSH FAILED'),
        );

        $errorEvents = [];
        $eventDispatcher->addListener(
            OnHandleMessageError::class,
            static function (OnHandleMessageError $event) use (&$errorEvents): void {
                $errorEvents[] = $event;
            },
        );

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            $eventDispatcher,
            [],
            new NullLogger(),
        );

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));
        $subscription = new Subscription('test', status: Status::Active);

        $error = $processor->process(1, $message, $subscription);

        // a listener which fails on the terminal event must not escape as an exception,
        // and must not leave the message without a terminal event
        self::assertNotNull($error);
        self::assertSame('FLUSH FAILED', $error->message);
        self::assertCount(1, $errorEvents);
        self::assertNull($subscription->position());
    }

    public function testFailingTerminalListenerWithoutSubscribeMethodIsReportedAsError(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            OnHandleMessageSuccess::class,
            static fn (): never => throw new RuntimeException('FLUSH FAILED'),
        );

        $processor = new MessageProcessor(
            new MetadataSubscriberAccessorRepository([$subscriber]),
            $eventDispatcher,
            [],
            new NullLogger(),
        );

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));
        $subscription = new Subscription('test', status: Status::Active);

        $error = $processor->process(1, $message, $subscription);

        self::assertNotNull($error);
        self::assertSame('FLUSH FAILED', $error->message);
    }

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

        self::assertNotNull($error);
        self::assertInstanceOf(NoSuitableResolver::class, $error->throwable);
    }
}
