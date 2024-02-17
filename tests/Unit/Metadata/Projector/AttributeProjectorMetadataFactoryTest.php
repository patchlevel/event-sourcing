<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Projector;

use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Metadata\Projector\AttributeProjectorMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Projector\ClassIsNotAProjector;
use Patchlevel\EventSourcing\Metadata\Projector\DuplicateSetupMethod;
use Patchlevel\EventSourcing\Metadata\Projector\DuplicateTeardownMethod;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Metadata\Projector\AttributeProjectorMetadataFactory */
final class AttributeProjectorMetadataFactoryTest extends TestCase
{
    public function testNotAProjection(): void
    {
        $this->expectException(ClassIsNotAProjector::class);

        $projection = new class {
        };

        $metadataFactory = new AttributeProjectorMetadataFactory();
        $metadataFactory->metadata($projection::class);
    }

    public function testEmptyProjection(): void
    {
        $projection = new #[Projector('foo', 1)]
        class {
        };

        $metadataFactory = new AttributeProjectorMetadataFactory();
        $metadata = $metadataFactory->metadata($projection::class);

        self::assertSame([], $metadata->subscribeMethods);
        self::assertNull($metadata->setupMethod);
        self::assertNull($metadata->teardownMethod);
        self::assertSame('foo', $metadata->name);
        self::assertSame(1, $metadata->version);
    }

    public function testStandardProjection(): void
    {
        $projection = new #[Projector('foo', 1)]
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

        $metadataFactory = new AttributeProjectorMetadataFactory();
        $metadata = $metadataFactory->metadata($projection::class);

        self::assertEquals(
            [ProfileVisited::class => ['handle']],
            $metadata->subscribeMethods,
        );

        self::assertSame('create', $metadata->setupMethod);
        self::assertSame('drop', $metadata->teardownMethod);
    }

    public function testMultipleHandlerOnOneMethod(): void
    {
        $projection = new #[Projector('foo', 1)]
        class {
            #[Subscribe(ProfileVisited::class)]
            #[Subscribe(ProfileCreated::class)]
            public function handle(): void
            {
            }
        };

        $metadataFactory = new AttributeProjectorMetadataFactory();
        $metadata = $metadataFactory->metadata($projection::class);

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
        $projection = new #[Projector('foo', 1)]
        class {
            #[Subscribe(Subscribe::ALL)]
            public function handle(): void
            {
            }
        };

        $metadataFactory = new AttributeProjectorMetadataFactory();
        $metadata = $metadataFactory->metadata($projection::class);

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

        $projection = new #[Projector('foo', 1)]
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

        $metadataFactory = new AttributeProjectorMetadataFactory();
        $metadataFactory->metadata($projection::class);
    }

    public function testDuplicateTeardownAttributeException(): void
    {
        $this->expectException(DuplicateTeardownMethod::class);

        $projection = new #[Projector('foo', 1)]
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

        $metadataFactory = new AttributeProjectorMetadataFactory();
        $metadataFactory->metadata($projection::class);
    }
}
