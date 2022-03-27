<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Event;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\FieldName;
use Patchlevel\EventSourcing\Attribute\Normalize;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\ClassIsNotAnEvent;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\EmailNormalizer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class AttributeEventMetadataFactoryTest extends TestCase
{
    public function testEmptyEvent(): void
    {
        $this->expectException(ClassIsNotAnEvent::class);

        $event = new class {
        };

        $metadataFactory = new AttributeEventMetadataFactory();
        $metadataFactory->metadata($event::class);
    }

    public function testEventWithoutProperties(): void
    {
        $event = new #[Event('profile_created')] class {
        };

        $metadataFactory = new AttributeEventMetadataFactory();
        $metadata = $metadataFactory->metadata($event::class);

        self::assertSame('profile_created', $metadata->name);
        self::assertCount(0, $metadata->properties);
    }

    public function testEventWithProperties(): void
    {
        $event = new #[Event('profile_created')] class {
            public function __construct(
                public string $name
            ) {}
        };

        $metadataFactory = new AttributeEventMetadataFactory();
        $metadata = $metadataFactory->metadata($event::class);

        self::assertSame('profile_created', $metadata->name);
        self::assertCount(1, $metadata->properties);

        $propertyMetadata = $metadata->properties[0];

        self::assertSame('name', $propertyMetadata->fieldName);
        self::assertNull($propertyMetadata->normalizer);
        self::assertInstanceOf(ReflectionProperty::class, $propertyMetadata->reflection);
    }

    public function testEventWithFieldName(): void
    {
        $event = new #[Event('profile_created')] class {
            public function __construct(
                #[FieldName('username')]
                public string $name
            ) {}
        };

        $metadataFactory = new AttributeEventMetadataFactory();
        $metadata = $metadataFactory->metadata($event::class);

        self::assertSame('profile_created', $metadata->name);
        self::assertCount(1, $metadata->properties);

        $propertyMetadata = $metadata->properties[0];

        self::assertSame('username', $propertyMetadata->fieldName);
        self::assertNull($propertyMetadata->normalizer);
        self::assertInstanceOf(ReflectionProperty::class, $propertyMetadata->reflection);
    }

    public function testEventWithNormalizer(): void
    {
        $event = new #[Event('profile_created')] class {
            public function __construct(
                #[Normalize(EmailNormalizer::class)]
                public Email $email
            ) {}
        };

        $metadataFactory = new AttributeEventMetadataFactory();
        $metadata = $metadataFactory->metadata($event::class);

        self::assertSame('profile_created', $metadata->name);
        self::assertCount(1, $metadata->properties);

        $propertyMetadata = $metadata->properties[0];

        self::assertSame('email', $propertyMetadata->fieldName);
        self::assertInstanceOf(EmailNormalizer::class, $propertyMetadata->normalizer);
        self::assertInstanceOf(ReflectionProperty::class, $propertyMetadata->reflection);
    }
}
