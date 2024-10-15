<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber;

use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\EventArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\MessageArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\NoSuitableResolver;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor */
final class MetadataSubscriberAccessorTest extends TestCase
{
    public function testId(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
        };

        $accessor = new MetadataSubscriberAccessor(
            $subscriber,
            (new AttributeSubscriberMetadataFactory())->metadata($subscriber::class),
            [],
        );

        self::assertEquals('profile', $accessor->id());
    }

    public function testGroup(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
        };

        $accessor = new MetadataSubscriberAccessor(
            $subscriber,
            (new AttributeSubscriberMetadataFactory())->metadata($subscriber::class),
            [],
        );

        self::assertEquals('default', $accessor->group());
    }

    public function testRunMode(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
        };

        $accessor = new MetadataSubscriberAccessor(
            $subscriber,
            (new AttributeSubscriberMetadataFactory())->metadata($subscriber::class),
            [],
        );

        self::assertEquals(RunMode::FromBeginning, $accessor->runMode());
    }

    public function testSubscribeMethod(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(Message $message): void
            {
                $this->message = $message;
            }
        };

        $accessor = new MetadataSubscriberAccessor(
            $subscriber,
            (new AttributeSubscriberMetadataFactory())->metadata($subscriber::class),
            [
                new MessageArgumentResolver(),
            ],
        );

        $result = $accessor->subscribeMethods(ProfileVisited::class);

        self::assertArrayHasKey(0, $result);

        $message = new Message(new ProfileVisited(ProfileId::fromString('1')));

        $result[0]($message);

        self::assertSame($message, $subscriber->message);
    }

    public function testMultipleSubscribeMethod(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileCreated::class)]
            public function onProfileCreated(Message $message): void
            {
            }

            #[Subscribe(ProfileCreated::class)]
            public function onFoo(Message $message): void
            {
            }
        };

        $accessor = new MetadataSubscriberAccessor(
            $subscriber,
            (new AttributeSubscriberMetadataFactory())->metadata($subscriber::class),
            [
                new MessageArgumentResolver(),
            ],
        );

        $result = $accessor->subscribeMethods(ProfileCreated::class);

        self::assertCount(2, $result);

        self::assertEquals([
            $subscriber->onProfileCreated(...),
            $subscriber->onFoo(...),
        ], $result);
    }

    public function testSubscribeAllMethod(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
            public Message|null $message = null;

            #[Subscribe('*')]
            public function on(Message $message): void
            {
                $this->message = $message;
            }
        };

        $accessor = new MetadataSubscriberAccessor(
            $subscriber,
            (new AttributeSubscriberMetadataFactory())->metadata($subscriber::class),
            [
                new MessageArgumentResolver(),
            ],
        );

        $result = $accessor->subscribeMethods(ProfileVisited::class);

        self::assertArrayHasKey(0, $result);

        $message = new Message(new ProfileVisited(ProfileId::fromString('1')));

        $result[0]($message);

        self::assertSame($message, $subscriber->message);
    }

    public function testNoResolver(): void
    {
        $this->expectException(NoSuitableResolver::class);

        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function on(Message $message): void
            {
            }
        };

        $accessor = new MetadataSubscriberAccessor(
            $subscriber,
            (new AttributeSubscriberMetadataFactory())->metadata($subscriber::class),
            [],
        );

        $accessor->subscribeMethods(ProfileVisited::class);
    }

    public function testMultipleResolver(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function on(Message $message): void
            {
                $this->message = $message;
            }
        };

        $accessor = new MetadataSubscriberAccessor(
            $subscriber,
            (new AttributeSubscriberMetadataFactory())->metadata($subscriber::class),
            [
                new EventArgumentResolver(),
                new MessageArgumentResolver(),
            ],
        );

        $result = $accessor->subscribeMethods(ProfileVisited::class);

        self::assertArrayHasKey(0, $result);

        $message = new Message(new ProfileVisited(ProfileId::fromString('1')));

        $result[0]($message);

        self::assertSame($message, $subscriber->message);
    }

    public function testSetupMethod(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
            #[Setup]
            public function method(): void
            {
            }
        };

        $accessor = new MetadataSubscriberAccessor(
            $subscriber,
            (new AttributeSubscriberMetadataFactory())->metadata($subscriber::class),
            [],
        );

        $result = $accessor->setupMethod();

        self::assertEquals($subscriber->method(...), $result);
    }

    public function testNotSetupMethod(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
        };

        $accessor = new MetadataSubscriberAccessor(
            $subscriber,
            (new AttributeSubscriberMetadataFactory())->metadata($subscriber::class),
            [],
        );

        $result = $accessor->setupMethod();

        self::assertNull($result);
    }

    public function testTeardownMethod(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
            #[Teardown]
            public function method(): void
            {
            }
        };

        $accessor = new MetadataSubscriberAccessor(
            $subscriber,
            (new AttributeSubscriberMetadataFactory())->metadata($subscriber::class),
            [],
        );

        $result = $accessor->teardownMethod();

        self::assertEquals($subscriber->method(...), $result);
    }

    public function testNotTeardownMethod(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
        };

        $accessor = new MetadataSubscriberAccessor(
            $subscriber,
            (new AttributeSubscriberMetadataFactory())->metadata($subscriber::class),
            [],
        );

        $result = $accessor->teardownMethod();

        self::assertNull($result);
    }

    public function testRealSubscriber(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
        };

        $accessor = new MetadataSubscriberAccessor(
            $subscriber,
            (new AttributeSubscriberMetadataFactory())->metadata($subscriber::class),
            [],
        );

        self::assertEquals($subscriber, $accessor->realSubscriber());
    }
}
