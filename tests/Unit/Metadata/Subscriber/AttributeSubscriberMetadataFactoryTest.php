<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Subscriber;

use Patchlevel\EventSourcing\Attribute\OverrideEventEmitting;
use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentTypeNotSupported;
use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\ClassIsNotASubscriber;
use Patchlevel\EventSourcing\Metadata\Subscriber\DuplicateSetupMethod;
use Patchlevel\EventSourcing\Metadata\Subscriber\DuplicateSubscribeMethod;
use Patchlevel\EventSourcing\Metadata\Subscriber\DuplicateTeardownMethod;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscribeMethodMetadata;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;

#[CoversClass(AttributeSubscriberMetadataFactory::class)]
final class AttributeSubscriberMetadataFactoryTest extends TestCase
{
    public function testNotASubscriber(): void
    {
        $this->expectException(ClassIsNotASubscriber::class);

        $subscriber = new class {
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testEmptySubscriber(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertSame([], $metadata->subscribeMethods);
        self::assertNull($metadata->setupMethod);
        self::assertNull($metadata->teardownMethod);
        self::assertSame('foo', $metadata->id);
        self::assertNull($metadata->overrideEventEmitting);
    }

    public function testOverrideEventEmitting(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        #[OverrideEventEmitting(true)]
        class {
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertTrue($metadata->overrideEventEmitting);
    }

    public function testProjector(): void
    {
        $subscriber = new #[Projector('foo')]
        class {
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertSame([], $metadata->subscribeMethods);
        self::assertNull($metadata->setupMethod);
        self::assertNull($metadata->teardownMethod);
        self::assertSame('foo', $metadata->id);
        self::assertSame('projector', $metadata->group);
        self::assertSame(RunMode::FromBeginning, $metadata->runMode);
    }

    public function testProcessor(): void
    {
        $subscriber = new #[Processor('foo')]
        class {
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertSame([], $metadata->subscribeMethods);
        self::assertNull($metadata->setupMethod);
        self::assertNull($metadata->teardownMethod);
        self::assertSame('foo', $metadata->id);
        self::assertSame('processor', $metadata->group);
        self::assertSame(RunMode::FromNow, $metadata->runMode);
    }

    public function testStandardSubscriber(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function handle(): void
            {
            }

            #[Setup]
            public function create(): void
            {
            }

            #[Teardown]
            public function drop(): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertEquals(
            [
                ProfileVisited::class => new SubscribeMethodMetadata('handle', []),
            ],
            $metadata->subscribeMethods,
        );

        self::assertSame('create', $metadata->setupMethod);
        self::assertSame('drop', $metadata->teardownMethod);
    }

    public function testMultipleHandlerOnOneMethod(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            #[Subscribe(ProfileCreated::class)]
            public function handle(): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertEquals(
            [
                ProfileVisited::class => new SubscribeMethodMetadata('handle', []),
                ProfileCreated::class => new SubscribeMethodMetadata('handle', []),
            ],
            $metadata->subscribeMethods,
        );
    }

    public function testSubscribeAll(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Subscribe(Subscribe::ALL)]
            public function handle(): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertEquals(
            [
                '*' => new SubscribeMethodMetadata('handle', []),
            ],
            $metadata->subscribeMethods,
        );
    }

    public function testSubscribeAttributes(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function profileVisited(Message $message): void
            {
            }

            #[Subscribe(ProfileCreated::class)]
            public function profileCreated(ProfileCreated $profileCreated, string $aggregateId): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertEquals(
            [
                ProfileVisited::class => new SubscribeMethodMetadata(
                    'profileVisited',
                    [new ArgumentMetadata('message', Type::object(Message::class))],
                ),

                ProfileCreated::class => new SubscribeMethodMetadata(
                    'profileCreated',
                    [
                        new ArgumentMetadata('profileCreated', Type::object(ProfileCreated::class)),
                        new ArgumentMetadata('aggregateId', Type::string()),
                    ],
                ),
            ],
            $metadata->subscribeMethods,
        );
    }

    public function testSubscribeNullableAttribute(): void
    {
        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function profileVisited(ProfileVisited|null $message): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertEquals(
            [
                ProfileVisited::class => new SubscribeMethodMetadata('profileVisited', [
                    new ArgumentMetadata('message', Type::nullable(Type::object(ProfileVisited::class))),
                ]),
            ],
            $metadata->subscribeMethods,
        );
    }

    public function testMissingArgumentType(): void
    {
        $this->expectException(ArgumentTypeNotSupported::class);

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            // phpcs:disable
            #[Subscribe(ProfileVisited::class)]
            public function profileVisited($message): void
            {
            }
            // phpcs:enable
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testSubscribeAllWithExplicitSubscribeMethod(): void
    {
        $this->expectException(DuplicateSubscribeMethod::class);

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function profileVisited(ProfileVisited $event): void
            {
            }

            #[Subscribe(Subscribe::ALL)]
            public function listenAll(ProfileVisited $event): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testSubscribeSameEvent(): void
    {
        $this->expectException(DuplicateSubscribeMethod::class);

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function profileVisited(ProfileVisited $event): void
            {
            }

            #[Subscribe(ProfileVisited::class)]
            public function profileVisitedAgain(ProfileVisited $event): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testDuplicateSetupAttributeException(): void
    {
        $this->expectException(DuplicateSetupMethod::class);

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Setup]
            public function create1(): void
            {
            }

            #[Setup]
            public function create2(): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }

    public function testDuplicateTeardownAttributeException(): void
    {
        $this->expectException(DuplicateTeardownMethod::class);

        $subscriber = new #[Subscriber('foo', RunMode::FromBeginning)]
        class {
            #[Teardown]
            public function drop1(): void
            {
            }

            #[Teardown]
            public function drop2(): void
            {
            }
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadataFactory->metadata($subscriber::class);
    }
}
