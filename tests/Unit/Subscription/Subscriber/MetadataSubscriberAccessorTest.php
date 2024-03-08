<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber;

use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
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
        );

        self::assertEquals(RunMode::FromBeginning, $accessor->runMode());
    }

    public function testSubscribeMethod(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileCreated::class)]
            public function onProfileCreated(Message $message): void
            {
            }
        };

        $accessor = new MetadataSubscriberAccessor(
            $subscriber,
            (new AttributeSubscriberMetadataFactory())->metadata($subscriber::class),
        );

        $result = $accessor->subscribeMethods(ProfileCreated::class);

        self::assertEquals([
            $subscriber->onProfileCreated(...),
        ], $result);
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
        );

        $result = $accessor->subscribeMethods(ProfileCreated::class);

        self::assertEquals([
            $subscriber->onProfileCreated(...),
            $subscriber->onFoo(...),
        ], $result);
    }

    public function testSubscribeAllMethod(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
            #[Subscribe('*')]
            public function onProfileCreated(Message $message): void
            {
            }
        };

        $accessor = new MetadataSubscriberAccessor(
            $subscriber,
            (new AttributeSubscriberMetadataFactory())->metadata($subscriber::class),
        );

        $result = $accessor->subscribeMethods(ProfileCreated::class);

        self::assertEquals([
            $subscriber->onProfileCreated(...),
        ], $result);
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
        );

        $result = $accessor->teardownMethod();

        self::assertNull($result);
    }
}
