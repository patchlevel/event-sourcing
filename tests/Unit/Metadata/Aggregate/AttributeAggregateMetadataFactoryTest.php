<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootIdNotFound;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\ArgumentTypeIsMissing;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\ArgumentTypeIsNotAClass;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\ClassIsNotAnAggregate;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\DuplicateApplyMethod;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\DuplicateEmptyApplyAttribute;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\MixedApplyAttributeUsage;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\Snapshot;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\AutoInitializableProfile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\MessageDeleted;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\NameChanged;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithAggregateStream;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithBrokenApplyBothUsage;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithBrokenApplyIntersection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithBrokenApplyMultipleApply;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithBrokenApplyNoType;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithBrokenApplyStringType;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithBrokenApplyUnionIntersection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithDuplicateApply;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithEmptyApply;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithoutAggregateAttribute;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithoutId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithSharedApplyContext;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithSnapshot;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithStream;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileWithSuppressAll;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\SplittingEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributeAggregateRootMetadataFactory::class)]
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

        self::assertSame('profile-{id}', $metadata->streamName);
        self::assertSame('profile-foo', $metadata->streamName('foo'));
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

    public function testStreamName(): void
    {
        $metadataFactory = new AttributeAggregateRootMetadataFactory();
        $metadata = $metadataFactory->metadata(ProfileWithStream::class);

        self::assertSame('other-{id}', $metadata->streamName);
        self::assertSame('other-foo', $metadata->streamName('foo'));
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

    public function testBrokenApplyWithUnionIntersectionType(): void
    {
        $metadataFactory = new AttributeAggregateRootMetadataFactory();
        $this->expectException(ArgumentTypeIsMissing::class);

        $metadataFactory->metadata(ProfileWithBrokenApplyUnionIntersection::class);
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

    public function testSuppressAll(): void
    {
        $metadataFactory = new AttributeAggregateRootMetadataFactory();
        $metadata = $metadataFactory->metadata(ProfileWithSuppressAll::class);

        self::assertTrue($metadata->suppressAll);
        self::assertSame([], $metadata->suppressEvents);
    }

    public function testSharedApplyContext(): void
    {
        $metadataFactory = new AttributeAggregateRootMetadataFactory();
        $metadata = $metadataFactory->metadata(ProfileWithSharedApplyContext::class);

        self::assertFalse($metadata->suppressAll);
        self::assertSame([ProfileCreated::class => true], $metadata->suppressEvents);
    }

    public function testMetadataCache(): void
    {
        $metadataFactory = new AttributeAggregateRootMetadataFactory();

        self::assertSame(
            $metadataFactory->metadata(Profile::class),
            $metadataFactory->metadata(Profile::class),
        );
    }

    public function testNotAnAggregate(): void
    {
        $metadataFactory = new AttributeAggregateRootMetadataFactory();

        $this->expectException(ClassIsNotAnAggregate::class);

        $metadataFactory->metadata(ProfileWithoutAggregateAttribute::class);
    }

    public function testAggregateRootIdNotFound(): void
    {
        $metadataFactory = new AttributeAggregateRootMetadataFactory();

        $this->expectException(AggregateRootIdNotFound::class);

        $metadataFactory->metadata(ProfileWithoutId::class);
    }

    public function testSnapshot(): void
    {
        $metadataFactory = new AttributeAggregateRootMetadataFactory();
        $metadata = $metadataFactory->metadata(ProfileWithSnapshot::class);

        self::assertEquals(new Snapshot('memory', 2, '1'), $metadata->snapshot);
    }

    public function testStreamNameFromString(): void
    {
        $metadataFactory = new AttributeAggregateRootMetadataFactory();
        $metadata = $metadataFactory->metadata(ProfileWithStream::class);

        self::assertSame('other-{id}', $metadata->streamName);
    }

    public function testStreamNameFromAggregateClass(): void
    {
        $metadataFactory = new AttributeAggregateRootMetadataFactory();
        $metadata = $metadataFactory->metadata(ProfileWithAggregateStream::class);

        self::assertSame('profile-{id}', $metadata->streamName);
    }

    public function testAutoInitializeMethod(): void
    {
        $metadataFactory = new AttributeAggregateRootMetadataFactory();
        $metadata = $metadataFactory->metadata(AutoInitializableProfile::class);

        self::assertSame('initialize', $metadata->autoInitializeMethod);
    }

    public function testApplyWithNotAClassType(): void
    {
        $metadataFactory = new AttributeAggregateRootMetadataFactory();

        $this->expectException(ArgumentTypeIsNotAClass::class);

        $metadataFactory->metadata(ProfileWithBrokenApplyStringType::class);
    }

    public function testDuplicateApplyMethod(): void
    {
        $metadataFactory = new AttributeAggregateRootMetadataFactory();

        $this->expectException(DuplicateApplyMethod::class);

        $metadataFactory->metadata(ProfileWithDuplicateApply::class);
    }
}
