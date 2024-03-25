<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event;

use Patchlevel\EventSourcing\Attribute\DataSubjectId;
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\PersonalData;
use Patchlevel\EventSourcing\Attribute\SplitStream;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\ClassIsNotAnEvent;
use Patchlevel\EventSourcing\Metadata\Event\DataSubjectIdIsPersonalData;
use Patchlevel\EventSourcing\Metadata\Event\MissingDataSubjectId;
use Patchlevel\EventSourcing\Metadata\Event\MultipleDataSubjectId;
use Patchlevel\Hydrator\Attribute\NormalizedName;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory */
final class AttributeEventMetadataFactoryTest extends TestCase
{
    public function testEmptyEvent(): void
    {
        $this->expectException(ClassIsNotAnEvent::class);

        $event = new class {
        };

        $metadataFactory = new AttributeEventMetadataFactory();
        $metadataFactory->metadata($event::class);
    }

    public function testEvent(): void
    {
        $event = new #[Event('profile_created')]
        class {
        };

        $metadataFactory = new AttributeEventMetadataFactory();
        $metadata = $metadataFactory->metadata($event::class);

        self::assertSame('profile_created', $metadata->name);
        self::assertSame(false, $metadata->splitStream);
        self::assertSame(null, $metadata->dataSubjectIdField);
        self::assertEmpty($metadata->propertyMetadata);
    }

    public function testSplitStream(): void
    {
        $event = new #[Event('profile_created')]
        #[SplitStream]
        class {
        };

        $metadataFactory = new AttributeEventMetadataFactory();
        $metadata = $metadataFactory->metadata($event::class);

        self::assertSame('profile_created', $metadata->name);
        self::assertSame(true, $metadata->splitStream);
        self::assertSame(null, $metadata->dataSubjectIdField);
        self::assertEmpty($metadata->propertyMetadata);
    }

    public function testPersonalData(): void
    {
        $event = new #[Event('profile_created')]
        class ('id', 'name') {
            public function __construct(
                #[DataSubjectId]
                #[NormalizedName('_id')]
                public string $id,
                #[PersonalData('fallback')]
                #[NormalizedName('_name')]
                public string $name,
            ) {
            }
        };

        $metadataFactory = new AttributeEventMetadataFactory();
        $metadata = $metadataFactory->metadata($event::class);

        self::assertSame('profile_created', $metadata->name);
        self::assertSame(false, $metadata->splitStream);
        self::assertSame('_id', $metadata->dataSubjectIdField);
        self::assertCount(2, $metadata->propertyMetadata);

        self::assertSame('id', $metadata->propertyMetadata['id']->propertyName);
        self::assertSame(false, $metadata->propertyMetadata['id']->isPersonalData);
        self::assertSame('_id', $metadata->propertyMetadata['id']->fieldName);
        self::assertSame(null, $metadata->propertyMetadata['id']->personalDataFallback);

        self::assertSame('name', $metadata->propertyMetadata['name']->propertyName);
        self::assertSame(true, $metadata->propertyMetadata['name']->isPersonalData);
        self::assertSame('_name', $metadata->propertyMetadata['name']->fieldName);
        self::assertSame('fallback', $metadata->propertyMetadata['name']->personalDataFallback);
    }

    public function testMissingDataSubjectId(): void
    {
        $event = new #[Event('profile_created')]
        class ('name') {
            public function __construct(
                #[PersonalData]
                public string $name,
            ) {
            }
        };

        $this->expectException(MissingDataSubjectId::class);

        $metadataFactory = new AttributeEventMetadataFactory();
        $metadataFactory->metadata($event::class);
    }

    public function testDataSubjectIdIsPersonalData(): void
    {
        $event = new #[Event('profile_created')]
        class ('name') {
            public function __construct(
                #[DataSubjectId]
                #[PersonalData]
                public string $name,
            ) {
            }
        };

        $this->expectException(DataSubjectIdIsPersonalData::class);

        $metadataFactory = new AttributeEventMetadataFactory();
        $metadataFactory->metadata($event::class);
    }

    public function testMultipleDataSubjectId(): void
    {
        $event = new #[Event('profile_created')]
        class ('id', 'name') {
            public function __construct(
                #[DataSubjectId]
                public string $id,
                #[DataSubjectId]
                public string $name,
            ) {
            }
        };

        $this->expectException(MultipleDataSubjectId::class);

        $metadataFactory = new AttributeEventMetadataFactory();
        $metadataFactory->metadata($event::class);
    }
}
