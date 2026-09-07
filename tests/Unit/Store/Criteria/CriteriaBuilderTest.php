<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store\Criteria;

use Patchlevel\EventSourcing\Store\Criteria\ArchivedCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\CriteriaBuilder;
use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ToPlayheadCriterion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CriteriaBuilder::class)]
final class CriteriaBuilderTest extends TestCase
{
    public function testEmpty(): void
    {
        $builder = new CriteriaBuilder();
        $criteria = $builder->build();

        self::assertEquals(new Criteria(), $criteria);
    }

    public function testFull(): void
    {
        $builder = new CriteriaBuilder();
        $criteria = $builder
            ->streamName('profile')
            ->fromIndex(1)
            ->fromPlayhead(1)
            ->archived(true)
            ->events(['foo', 'bar'])
            ->build();

        self::assertEquals(
            new Criteria(
                new StreamCriterion('profile'),
                new FromIndexCriterion(1),
                new FromPlayheadCriterion(1),
                new ArchivedCriterion(true),
                new EventsCriterion(['foo', 'bar']),
            ),
            $criteria,
        );
    }

    public function testStreamNames(): void
    {
        $builder = new CriteriaBuilder();
        $criteria = $builder
            ->streamName(['profile-1', 'profile-2'])
            ->build();

        self::assertEquals(
            new Criteria(new StreamCriterion('profile-1', 'profile-2')),
            $criteria,
        );
    }

    public function testResetStreamName(): void
    {
        $builder = new CriteriaBuilder();
        $criteria = $builder
            ->streamName('profile-1')
            ->streamName(null)
            ->build();

        self::assertEquals(new Criteria(), $criteria);
    }

    public function testToPlayhead(): void
    {
        $builder = new CriteriaBuilder();
        $criteria = $builder
            ->toPlayhead(10)
            ->build();

        self::assertEquals(
            new Criteria(new ToPlayheadCriterion(10)),
            $criteria,
        );
    }
}
