<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\Query;
use Patchlevel\EventSourcing\Store\SubQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AppendCondition::class)]
final class AppendConditionTest extends TestCase
{
    public function testInstantiate(): void
    {
        $query = new Query(new SubQuery(['foo']));

        $condition = new AppendCondition($query, 42);

        self::assertSame($query, $condition->query);
        self::assertSame(42, $condition->highestSequenceNumber);
    }

    public function testInstantiateWithDefaults(): void
    {
        $condition = new AppendCondition();

        self::assertEquals(new Query(), $condition->query);
        self::assertNull($condition->highestSequenceNumber);
    }

    public function testInstantiateWithZeroSequenceAndQuery(): void
    {
        $query = new Query(new SubQuery(['foo']));

        $condition = new AppendCondition($query, 0);

        self::assertSame($query, $condition->query);
        self::assertSame(0, $condition->highestSequenceNumber);
    }

    public function testNonEmptyQueryWithoutSequenceNumberIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AppendCondition(new Query(new SubQuery(['foo'])));
    }
}
