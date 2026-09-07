<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Subscriber;

use Patchlevel\EventSourcing\Attribute\Cleanup;
use Patchlevel\EventSourcing\Attribute\OnFailed;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Throwable;

#[CoversClass(MetadataSubscriberAccessor::class)]
final class MetadataSubscriberAccessorTest extends TestCase
{
    /** @return MetadataSubscriberAccessor<object> */
    private function accessor(object $subscriber): MetadataSubscriberAccessor
    {
        return new MetadataSubscriberAccessor(
            $subscriber,
            (new AttributeSubscriberMetadataFactory())->metadata($subscriber::class),
        );
    }

    public function testSubscribeMethod(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(Message $message): void
            {
            }
        };

        $result = $this->accessor($subscriber)->subscribeMethods(ProfileVisited::class);

        self::assertArrayHasKey(0, $result);
        self::assertSame('onProfileVisited', $result[0]->name);
    }

    public function testSubscribeAllMethod(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
            #[Subscribe('*')]
            public function on(Message $message): void
            {
            }
        };

        $result = $this->accessor($subscriber)->subscribeMethods(ProfileVisited::class);

        self::assertArrayHasKey(0, $result);
        self::assertSame('on', $result[0]->name);
    }

    public function testNoSubscribeMethod(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
        };

        self::assertSame([], $this->accessor($subscriber)->subscribeMethods(ProfileVisited::class));
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

        self::assertEquals($subscriber->method(...), $this->accessor($subscriber)->setupMethod());
    }

    public function testNotSetupMethod(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
        };

        self::assertNull($this->accessor($subscriber)->setupMethod());
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

        self::assertEquals($subscriber->method(...), $this->accessor($subscriber)->teardownMethod());
    }

    public function testNotTeardownMethod(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
        };

        self::assertNull($this->accessor($subscriber)->teardownMethod());
    }

    public function testRealSubscriber(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
        };

        self::assertEquals($subscriber, $this->accessor($subscriber)->subscriber());
    }

    public function testMetadata(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
        };

        $accessor = $this->accessor($subscriber);

        self::assertSame('profile', $accessor->metadata()->id);
        self::assertSame($subscriber, $accessor->subscriber());
    }

    public function testCleanupMethod(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
            public bool $called = false;

            #[Cleanup]
            public function cleanup(): void
            {
                $this->called = true;
            }
        };

        $cleanupMethod = $this->accessor($subscriber)->cleanupMethod();

        self::assertNotNull($cleanupMethod);

        $cleanupMethod();

        self::assertTrue($subscriber->called);
    }

    public function testNoCleanupMethod(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
        };

        self::assertNull($this->accessor($subscriber)->cleanupMethod());
    }

    public function testFailedMethod(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
            public bool $called = false;
            public Message|null $message = null;
            public Throwable|null $throwable = null;

            #[OnFailed]
            public function onFailed(Message $message, Throwable $throwable): void
            {
                $this->called = true;
                $this->message = $message;
                $this->throwable = $throwable;
            }
        };

        $failedMethod = $this->accessor($subscriber)->failedMethod();
        $message = new Message(new stdClass());
        $throwable = new RuntimeException();

        self::assertNotNull($failedMethod);

        $failedMethod($message, $throwable);

        self::assertTrue($subscriber->called);
        self::assertSame($message, $subscriber->message);
        self::assertSame($throwable, $subscriber->throwable);
    }

    public function testNoFailedMethod(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
        };

        self::assertNull($this->accessor($subscriber)->failedMethod());
    }

    public function testEvents(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(Message $message): void
            {
            }
        };

        self::assertSame([ProfileVisited::class], $this->accessor($subscriber)->events());
    }

    public function testSubscribeMethodsCache(): void
    {
        $subscriber = new #[Subscriber('profile', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function onProfileVisited(Message $message): void
            {
            }
        };

        $accessor = $this->accessor($subscriber);

        self::assertSame(
            $accessor->subscribeMethods(ProfileVisited::class),
            $accessor->subscribeMethods(ProfileVisited::class),
        );
    }
}
