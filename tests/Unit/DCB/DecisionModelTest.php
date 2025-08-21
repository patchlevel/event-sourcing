<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\DCB;

use LogicException;
use OutOfBoundsException;
use Patchlevel\EventSourcing\DCB\DecisionModel;
use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\Query;
use Patchlevel\EventSourcing\Store\SubQuery;
use PHPUnit\Framework\TestCase;

final class DecisionModelTest extends TestCase
{
    private function createAppendCondition(): AppendCondition
    {
        $query = new Query(new SubQuery(['tag:foo']));

        return new AppendCondition($query, 42);
    }

    public function testArrayAccessGetAndAppendCondition(): void
    {
        $appendCondition = $this->createAppendCondition();

        $model = new DecisionModel([
            'foo' => 123,
            'bar' => 'baz',
        ], $appendCondition);

        self::assertSame(123, $model['foo']);
        self::assertSame('baz', $model['bar']);
        self::assertSame($appendCondition, $model->appendCondition);
    }

    public function testOffsetExists(): void
    {
        $model = new DecisionModel(['a' => 1], $this->createAppendCondition());

        self::assertTrue(isset($model['a']));
        /** @phpstan-ignore-next-line */
        self::assertFalse(isset($model['b']));
    }

    public function testUnknownKeyThrowsOutOfBounds(): void
    {
        $model = new DecisionModel(['a' => 1], $this->createAppendCondition());

        $this->expectException(OutOfBoundsException::class);
        // access non-existing key
        /** @phpstan-ignore-next-line */
        $unused = $model['b'];
    }

    public function testImmutableSetThrows(): void
    {
        $model = new DecisionModel(['a' => 1], $this->createAppendCondition());

        $this->expectException(LogicException::class);
        /** @phpstan-ignore-next-line */
        $model['a'] = 2;
    }

    public function testImmutableUnsetThrows(): void
    {
        $model = new DecisionModel(['a' => 1], $this->createAppendCondition());

        $this->expectException(LogicException::class);
        /** @phpstan-ignore-next-line */
        unset($model['a']);
    }
}
