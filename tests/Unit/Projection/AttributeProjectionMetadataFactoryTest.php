<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Projection\AttributeProjectionMetadataFactory;
use Patchlevel\EventSourcing\Projection\MetadataException;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

class AttributeProjectionMetadataFactoryTest extends TestCase
{
    public function testEmptyProjection(): void
    {
        $projection = new class implements Projection {
        };

        $metadataFactory = new AttributeProjectionMetadataFactory();
        $metadata = $metadataFactory->metadata($projection);

        self::assertSame([], $metadata->handleMethods);
        self::assertNull($metadata->createMethod);
        self::assertNull($metadata->dropMethod);
    }

    public function testStandardProjection(): void
    {
        $projection = new class implements Projection {
            #[Handle(ProfileVisited::class)]
            public function handle(): void
            {
            }

            #[Create]
            public function create(): void
            {
            }

            #[Drop]
            public function drop(): void
            {
            }
        };

        $metadataFactory = new AttributeProjectionMetadataFactory();
        $metadata = $metadataFactory->metadata($projection);

        self::assertEquals(
            [ProfileVisited::class => 'handle'],
            $metadata->handleMethods
        );

        self::assertSame('create', $metadata->createMethod);
        self::assertSame('drop', $metadata->dropMethod);
    }

    public function testMultipleHandlerOnOneMethod(): void
    {
        $projection = new class implements Projection {
            #[Handle(ProfileVisited::class)]
            #[Handle(ProfileCreated::class)]
            public function handle(): void
            {
            }
        };

        $metadataFactory = new AttributeProjectionMetadataFactory();
        $metadata = $metadataFactory->metadata($projection);

        self::assertEquals(
            [
                ProfileVisited::class => 'handle',
                ProfileCreated::class => 'handle',
            ],
            $metadata->handleMethods
        );
    }

    public function testDuplicateCreateAttributeException(): void
    {
        $this->expectException(MetadataException::class);

        $projection = new class implements Projection {
            #[Create]
            public function create1(): void
            {
            }

            #[Create]
            public function create2(): void
            {
            }
        };

        $metadataFactory = new AttributeProjectionMetadataFactory();
        $metadataFactory->metadata($projection);
    }

    public function testDuplicateDropAttributeException(): void
    {
        $this->expectException(MetadataException::class);

        $projection = new class implements Projection {
            #[Drop]
            public function drop1(): void
            {
            }

            #[Drop]
            public function drop2(): void
            {
            }
        };

        $metadataFactory = new AttributeProjectionMetadataFactory();
        $metadataFactory->metadata($projection);
    }
}
