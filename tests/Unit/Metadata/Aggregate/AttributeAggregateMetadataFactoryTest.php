<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Aggregate;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\ArgumentTypeIsMissing;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootMetadataFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\DuplicateEmptyApplyAttribute;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\MixedApplyAttributeUsage;
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
use PHPUnit\Framework\TestCase;

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
            ],
            $metadata->applyMethods
        );
        self::assertFalse($metadata->suppressAll);
        self::assertSame(
            [MessageDeleted::class => true],
            $metadata->suppressEvents
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
            $metadata->applyMethods
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

    /** @psalm-suppress UnevaluatedCode */
    public function testBrokenApplyWithIntersectionType(): void
    {
        $this->markTestSkipped('Needs Pslam to not crash on the intersection type. Should be at psalm 5.');

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
}
