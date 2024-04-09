<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\DataSubjectId;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Attribute\PersonalData;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\ArgumentTypeIsMissing;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\DuplicateEmptyApplyAttribute;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\MissingDataSubjectId;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\MixedApplyAttributeUsage;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\MultipleDataSubjectId;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\SubjectIdAndPersonalDataConflict;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\MessageDeleted;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\NameChanged;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithBrokenApplyBothUsage;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithBrokenApplyIntersection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithBrokenApplyMultipleApply;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithBrokenApplyNoType;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithEmptyApply;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\SplittingEvent;
use Patchlevel\Hydrator\Attribute\NormalizedName;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootMetadataFactory */
final class AttributeAggregateMetadataFactoryTest extends TestCase
{
    public function testProfile(): void
    {
        $metadataFactory = new AttributeAggregateRootMetadataFactory();
        $metadata = $metadataFactory->metadata(Profile::class);

        self::assertSame(
            [
                ProfileCreated::class => 'applyProfileCreated',
                ProfileVisited::class => 'applyProfileCreated',
                NameChanged::class => 'applyNameChanged',
                SplittingEvent::class => 'applySplittingEvent',
            ],
            $metadata->applyMethods,
        );
        self::assertFalse($metadata->suppressAll);
        self::assertSame(
            [MessageDeleted::class => true],
            $metadata->suppressEvents,
        );
    }

    public function testApplyWithNoEventClass(): void
    {
        $metadataFactory = new AttributeAggregateRootMetadataFactory();
        $metadata = $metadataFactory->metadata(ProfileWithEmptyApply::class);

        self::assertSame(
            [
                ProfileCreated::class => 'applyProfileCreated',
                ProfileVisited::class => 'applyProfileCreated',
                NameChanged::class => 'applyNameChanged',
            ],
            $metadata->applyMethods,
        );
        self::assertFalse($metadata->suppressAll);
        self::assertSame([], $metadata->suppressEvents);
    }

    public function testBrokenApplyWithNoType(): void
    {
        $metadataFactory = new AttributeAggregateRootMetadataFactory();
        $this->expectException(ArgumentTypeIsMissing::class);

        $metadataFactory->metadata(ProfileWithBrokenApplyNoType::class);
    }

    public function testBrokenApplyWithIntersectionType(): void
    {
        $metadataFactory = new AttributeAggregateRootMetadataFactory();
        $this->expectException(ArgumentTypeIsMissing::class);

        $metadataFactory->metadata(ProfileWithBrokenApplyIntersection::class);
    }

    public function testBrokenApplyWithMultipleApply(): void
    {
        $metadataFactory = new AttributeAggregateRootMetadataFactory();
        $this->expectException(DuplicateEmptyApplyAttribute::class);

        $metadataFactory->metadata(ProfileWithBrokenApplyMultipleApply::class);
    }

    public function testBrokenApplyWithBothUsages(): void
    {
        $metadataFactory = new AttributeAggregateRootMetadataFactory();
        $this->expectException(MixedApplyAttributeUsage::class);

        $metadataFactory->metadata(ProfileWithBrokenApplyBothUsage::class);
    }

    public function testPersonalData(): void
    {
        $event = new #[Aggregate('profile')]
        class ('id', 'name') {
            public function __construct(
                #[Id]
                #[DataSubjectId]
                #[NormalizedName('_id')]
                public string $id,
                #[PersonalData('fallback')]
                #[NormalizedName('_name')]
                public string $name,
            ) {
            }
        };

        $metadataFactory = new AttributeAggregateRootMetadataFactory();
        $metadata = $metadataFactory->metadata($event::class);

        self::assertSame('profile', $metadata->name);
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
        $event = new #[Aggregate('profile')]
        class ('name') {
            public function __construct(
                #[Id]
                #[PersonalData]
                public string $name,
            ) {
            }
        };

        $this->expectException(MissingDataSubjectId::class);

        $metadataFactory = new AttributeAggregateRootMetadataFactory();
        $metadataFactory->metadata($event::class);
    }

    public function testDataSubjectIdIsPersonalData(): void
    {
        $event = new #[Aggregate('profile')]
        class ('name') {
            public function __construct(
                #[Id]
                #[DataSubjectId]
                #[PersonalData]
                public string $name,
            ) {
            }
        };

        $this->expectException(SubjectIdAndPersonalDataConflict::class);

        $metadataFactory = new AttributeAggregateRootMetadataFactory();
        $metadataFactory->metadata($event::class);
    }

    public function testMultipleDataSubjectId(): void
    {
        $aggregate = new #[Aggregate('profile')]
        class ('id', 'name') {
            public function __construct(
                #[Id]
                #[DataSubjectId]
                public string $id,
                #[DataSubjectId]
                public string $name,
            ) {
            }
        };

        $this->expectException(MultipleDataSubjectId::class);

        $metadataFactory = new AttributeAggregateRootMetadataFactory();
        $metadataFactory->metadata($aggregate::class);
    }
}
