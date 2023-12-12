<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projection;

use Generator;
use InvalidArgumentException;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\Projection\ProjectionId */
final class ProjectionIdTest extends TestCase
{
    public function testProjectionId(): void
    {
        $projectionId = new ProjectionId(
            'test',
            1,
        );

        self::assertSame('test', $projectionId->name());
        self::assertSame(1, $projectionId->version());
        self::assertSame('test-1', $projectionId->toString());
    }

    public function testEquals(): void
    {
        $a = new ProjectionId(
            'test',
            1,
        );

        $b = new ProjectionId(
            'test',
            1,
        );

        $c = new ProjectionId(
            'foo',
            1,
        );

        $d = new ProjectionId(
            'test',
            2,
        );

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
        self::assertFalse($a->equals($d));
    }

    /** @dataProvider validFromStringProvider */
    public function testValidFromString(string $string, string $name, int $version): void
    {
        $projectionId = ProjectionId::fromString($string);

        self::assertSame($name, $projectionId->name());
        self::assertSame($version, $projectionId->version());
        self::assertSame($string, $projectionId->toString());
    }

    /** @return Generator<array-key, array{string, string, int}> */
    public static function validFromStringProvider(): Generator
    {
        yield ['hotel-1', 'hotel', 1];
        yield ['hotel-bar-1', 'hotel-bar', 1];
        yield ['hotel-bar--1', 'hotel-bar-', 1];
    }

    /** @dataProvider invalidFromStringProvider */
    public function testInvalidFromString(string $string): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProjectionId::fromString($string);
    }

    /** @return Generator<array-key, array{string}> */
    public static function invalidFromStringProvider(): Generator
    {
        yield ['hotel'];
        yield ['hotel-bar'];
    }
}
