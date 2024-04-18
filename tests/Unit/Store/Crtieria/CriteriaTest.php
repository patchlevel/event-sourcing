<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store\Crtieria;

use Patchlevel\EventSourcing\Store\Criteria\AggregateIdCriterion;
use Patchlevel\EventSourcing\Store\Criteria\AggregateNameCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\CriterionNotFound;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Store\Criteria\Criteria */
final class CriteriaTest extends TestCase
{
    public function testEmpty(): void
    {
        $criteria = new Criteria();

        self::assertEquals([], $criteria->all());
    }

    public function testWithCriterion(): void
    {
        $criteria = new Criteria(new AggregateNameCriterion('profile'));

        self::assertEquals([
            new AggregateNameCriterion('profile'),
        ], $criteria->all());
    }

    public function testHasCriterion(): void
    {
        $criteria = new Criteria(new AggregateNameCriterion('profile'));

        self::assertTrue($criteria->has(AggregateNameCriterion::class));
        self::assertFalse($criteria->has(AggregateIdCriterion::class));
    }

    public function testGetCriterion(): void
    {
        $criteria = new Criteria(new AggregateNameCriterion('profile'));

        self::assertEquals(new AggregateNameCriterion('profile'), $criteria->get(AggregateNameCriterion::class));
    }

    public function testCriterionNotFound(): void
    {
        $this->expectException(CriterionNotFound::class);

        $criteria = new Criteria();
        $criteria->get(AggregateNameCriterion::class);
    }

    public function testAddCriterion(): void
    {
        $criteria = new Criteria(new AggregateNameCriterion('profile'));
        $criteria = $criteria->add(new AggregateIdCriterion('1'));

        self::assertEquals([
            new AggregateNameCriterion('profile'),
            new AggregateIdCriterion('1'),
        ], $criteria->all());
    }

    public function testAddCriterionWithSameType(): void
    {
        $criteria = new Criteria(new AggregateNameCriterion('profile'));
        $criteria = $criteria->add(new AggregateNameCriterion('test'));

        self::assertEquals([
            new AggregateNameCriterion('test'),
        ], $criteria->all());
    }

    public function testRemoveCriterion(): void
    {
        $criteria = new Criteria(new AggregateNameCriterion('profile'));
        $criteria = $criteria->add(new AggregateIdCriterion('1'));
        $criteria = $criteria->remove(AggregateNameCriterion::class);

        self::assertEquals([
            new AggregateIdCriterion('1'),
        ], $criteria->all());
    }

    public function testRemoveCriterionNotFound(): void
    {
        $criteria = new Criteria(new AggregateNameCriterion('profile'));
        $criteria->remove(AggregateIdCriterion::class);

        self::assertEquals([
            new AggregateNameCriterion('profile'),
        ], $criteria->all());
    }
}
