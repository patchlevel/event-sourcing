<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store\Crtieria;

use Patchlevel\EventSourcing\Store\Criteria\AggregateIdCriterion;
use Patchlevel\EventSourcing\Store\Criteria\AggregateNameCriterion;
use Patchlevel\EventSourcing\Store\Criteria\ArchivedCriterion;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\CriteriaBuilder;
use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromPlayheadCriterion;
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
            ->aggregateName('profile')
            ->aggregateId('1')
            ->fromIndex(1)
            ->fromPlayhead(1)
            ->archived(true)
            ->events(['foo', 'bar'])
            ->build();

        self::assertEquals(
            new Criteria(
                new AggregateNameCriterion('profile'),
                new AggregateIdCriterion('1'),
                new FromIndexCriterion(1),
                new FromPlayheadCriterion(1),
                new ArchivedCriterion(true),
                new EventsCriterion(['foo', 'bar']),
            ),
            $criteria,
        );
    }
}
