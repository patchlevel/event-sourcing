<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store\Crtieria;

use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\CriterionNotFound;
use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Criteria::class)]
final class CriteriaTest extends TestCase
{
    public function testEmpty(): void
    {
        $criteria = new Criteria();

        self::assertEquals([], $criteria->all());
    }

    public function testWithCriterion(): void
    {
        $criteria = new Criteria(new StreamCriterion('profile'));

        self::assertEquals([
            new StreamCriterion('profile'),
        ], $criteria->all());
    }

    public function testHasCriterion(): void
    {
        $criteria = new Criteria(new StreamCriterion('profile'));

        self::assertTrue($criteria->has(StreamCriterion::class));
        self::assertFalse($criteria->has(FromPlayheadCriterion::class));
    }

    public function testGetCriterion(): void
    {
        $criteria = new Criteria(new StreamCriterion('profile'));

        self::assertEquals(new StreamCriterion('profile'), $criteria->get(StreamCriterion::class));
    }

    public function testCriterionNotFound(): void
    {
        $this->expectException(CriterionNotFound::class);

        $criteria = new Criteria();
        $criteria->get(StreamCriterion::class);
    }

    public function testAddCriterion(): void
    {
        $criteria = new Criteria(new StreamCriterion('profile'));
        $criteria = $criteria->add(new FromPlayheadCriterion(1));

        self::assertEquals([
            new StreamCriterion('profile'),
            new FromPlayheadCriterion(1),
        ], $criteria->all());
    }

    public function testAddCriterionWithSameType(): void
    {
        $criteria = new Criteria(new StreamCriterion('profile'));
        $criteria = $criteria->add(new StreamCriterion('test'));

        self::assertEquals([
            new StreamCriterion('test'),
        ], $criteria->all());
    }

    public function testRemoveCriterion(): void
    {
        $criteria = new Criteria(new StreamCriterion('profile'));
        $criteria = $criteria->add(new FromPlayheadCriterion(1));
        $criteria = $criteria->remove(StreamCriterion::class);

        self::assertEquals([
            new FromPlayheadCriterion(1),
        ], $criteria->all());
    }

    public function testRemoveCriterionNotFound(): void
    {
        $criteria = new Criteria(new StreamCriterion('profile'));
        $criteria->remove(FromPlayheadCriterion::class);

        self::assertEquals([
            new StreamCriterion('profile'),
        ], $criteria->all());
    }
}
