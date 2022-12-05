<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Generator;
use InvalidArgumentException;
use Patchlevel\EventSourcing\Projection\ProjectorId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\ProjectorId */
final class ProjectorIdTest extends TestCase
{
    public function testProjectorId(): void
    {
        $projectorId = new ProjectorId(
            'test',
            1
        );

        self::assertSame('test', $projectorId->name());
        self::assertSame(1, $projectorId->version());
        self::assertSame('test-1', $projectorId->toString());
    }

    public function testEquals(): void
    {
        $a = new ProjectorId(
            'test',
            1
        );

        $b = new ProjectorId(
            'test',
            1
        );

        $c = new ProjectorId(
            'foo',
            1
        );

        $d = new ProjectorId(
            'test',
            2
        );

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
        self::assertFalse($a->equals($d));
    }

    /**
     * @dataProvider validFromStringProvider
     */
    public function testValidFromString(string $string, string $name, int $version): void
    {
        $projectorId = ProjectorId::fromString($string);

        self::assertSame($name, $projectorId->name());
        self::assertSame($version, $projectorId->version());
        self::assertSame($string, $projectorId->toString());
    }

    /**
     * @return Generator<array-key, array{string, string, int}>
     */
    public function validFromStringProvider(): Generator
    {
        yield ['hotel-1', 'hotel', 1];
        yield ['hotel-bar-1', 'hotel-bar', 1];
        yield ['hotel-bar--1', 'hotel-bar-', 1];
    }

    /**
     * @dataProvider invalidFromStringProvider
     */
    public function testInvalidFromString(string $string): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProjectorId::fromString($string);
    }

    /**
     * @return Generator<array-key, array{string}>
     */
    public function invalidFromStringProvider(): Generator
    {
        yield ['hotel'];
        yield ['hotel-bar'];
    }
}
