<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Subscriber;

use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\ClassIsNotASubscriber;
use Patchlevel\EventSourcing\Metadata\Subscriber\DuplicateSetupMethod;
use Patchlevel\EventSourcing\Metadata\Subscriber\DuplicateTeardownMethod;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory */
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
        $subscriber = new #[Subscriber('foo')]
        class {
        };

        $metadataFactory = new AttributeSubscriberMetadataFactory();
        $metadata = $metadataFactory->metadata($subscriber::class);

        self::assertSame([], $metadata->subscribeMethods);
        self::assertNull($metadata->setupMethod);
        self::assertNull($metadata->teardownMethod);
        self::assertSame('foo', $metadata->id);
    }

    public function testStandardSubscriber(): void
    {
        $subscriber = new #[Subscriber('foo')]
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
            [ProfileVisited::class => ['handle']],
            $metadata->subscribeMethods,
        );

        self::assertSame('create', $metadata->setupMethod);
        self::assertSame('drop', $metadata->teardownMethod);
    }

    public function testMultipleHandlerOnOneMethod(): void
    {
        $subscriber = new #[Subscriber('foo')]
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
                ProfileVisited::class => ['handle'],
                ProfileCreated::class => ['handle'],
            ],
            $metadata->subscribeMethods,
        );
    }

    public function testSubscribeAll(): void
    {
        $subscriber = new #[Subscriber('foo')]
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
                '*' => ['handle'],
            ],
            $metadata->subscribeMethods,
        );
    }

    public function testDuplicateSetupAttributeException(): void
    {
        $this->expectException(DuplicateSetupMethod::class);

        $subscriber = new #[Subscriber('foo')]
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

        $subscriber = new #[Subscriber('foo')]
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
