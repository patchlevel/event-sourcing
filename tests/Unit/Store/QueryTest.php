<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Generator;
use Patchlevel\EventSourcing\Store\Query;
use Patchlevel\EventSourcing\Store\SubQuery;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QueryTest extends TestCase
{
    public function testEmptyByDefault(): void
    {
        $query = new Query();

        self::assertSame([], $query->subQueries);
    }

    public function testConstructWithSubqueriesKeepsOrder(): void
    {
        $sq1 = new SubQuery(['a'], [ProfileCreated::class], 's1');
        $sq2 = new SubQuery(['b'], [ProfileVisited::class], 's2', true);

        $query = new Query($sq1, $sq2);

        self::assertEquals([$sq1, $sq2], $query->subQueries);
    }

    public function testAdd(): void
    {
        $sq1 = new SubQuery(['a'], [ProfileCreated::class], 's1');
        $sq2 = new SubQuery(['b'], [ProfileVisited::class], 's2');

        $q1 = new Query($sq1);
        $q2 = $q1->add($sq2);

        self::assertNotSame($q1, $q2);
        self::assertEquals([$sq1, $sq2], $q2->subQueries);
    }

    #[DataProvider('providerForOptimize')]
    public function testOptimize(Query $before, Query $after): void
    {
        self::assertEquals($after, $before->optimize());
    }

    /** @return Generator<string, array{Query, Query}> */
    public static function providerForOptimize(): Generator
    {
        yield 'no subqueries' => [
            new Query(),
            new Query(),
        ];

        yield 'single subquery' => [
            new Query(
                new SubQuery(
                    ['a'],
                    [ProfileCreated::class],
                    's1',
                ),
            ),
            new Query(
                new SubQuery(
                    ['a'],
                    [ProfileCreated::class],
                    's1',
                ),
            ),
        ];

        yield 'equal subqueries' => [
            new Query(
                new SubQuery(
                    ['a'],
                    [ProfileCreated::class],
                    's1',
                ),
                new SubQuery(
                    ['a'],
                    [ProfileCreated::class],
                    's1',
                ),
            ),
            new Query(
                new SubQuery(
                    ['a'],
                    [ProfileCreated::class],
                    's1',
                ),
            ),
        ];

        yield 'equal subqueries 3 times' => [
            new Query(
                new SubQuery(
                    ['a'],
                    [ProfileCreated::class],
                    's1',
                ),
                new SubQuery(
                    ['a'],
                    [ProfileCreated::class],
                    's1',
                ),
                new SubQuery(
                    ['a'],
                    [ProfileCreated::class],
                    's1',
                ),
            ),
            new Query(
                new SubQuery(
                    ['a'],
                    [ProfileCreated::class],
                    's1',
                ),
            ),
        ];

        yield 'empty sub query includes all' => [
            new Query(
                new SubQuery(
                    ['a'],
                    [ProfileCreated::class],
                    's1',
                ),
                new SubQuery(
                    ['b'],
                    [ProfileVisited::class],
                    's2',
                    true,
                ),
                new SubQuery(),
            ),
            new Query(),
        ];

        yield 'partial overlap subqueries' => [
            new Query(
                new SubQuery(
                    ['a'],
                    [ProfileCreated::class],
                ),
                new SubQuery(
                    ['a'],
                ),
                new SubQuery(
                    [],
                    [ProfileCreated::class],
                ),
            ),
            new Query(
                new SubQuery(
                    ['a'],
                ),
                new SubQuery(
                    [],
                    [ProfileCreated::class],
                ),
            ),
        ];

        yield 'empty sub query' => [
            new Query(
                new SubQuery(),
                new SubQuery(
                    ['a'],
                ),
                new SubQuery(
                    [],
                    [ProfileCreated::class],
                ),
            ),
            new Query(),
        ];

        yield 'empty sub query with only last event' => [
            new Query(
                new SubQuery(
                    onlyLastEvent: true,
                ),
                new SubQuery(
                    ['a'],
                ),
                new SubQuery(
                    [],
                    [ProfileCreated::class],
                ),
            ),
            new Query(
                new SubQuery(
                    onlyLastEvent: true,
                ),
                new SubQuery(
                    ['a'],
                ),
                new SubQuery(
                    [],
                    [ProfileCreated::class],
                ),
            ),
        ];
    }
}
