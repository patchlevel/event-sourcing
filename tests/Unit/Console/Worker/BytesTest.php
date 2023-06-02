<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Worker;

use Generator;
use Patchlevel\EventSourcing\Console\Worker\Bytes;
use Patchlevel\EventSourcing\Console\Worker\InvalidFormat;
use PHPUnit\Framework\TestCase;

final class BytesTest extends TestCase
{
    public function testParseInvalidUnit(): void
    {
        $this->expectException(InvalidFormat::class);

        Bytes::parseFromString('505Foo');
    }

    public function testParseInvalidNegativeNumber(): void
    {
        $this->expectException(InvalidFormat::class);

        Bytes::parseFromString('-5GB');
    }

    /** @dataProvider validParseDataProvider */
    public function testValidParse(string $string, int $expectedBytes): void
    {
        $bytes = Bytes::parseFromString($string);

        self::assertSame($expectedBytes, $bytes->value());
    }

    /** @return Generator<array-key, array{string, int}> */
    public function validParseDataProvider(): Generator
    {
        yield ['50', 50];
        yield ['50B', 50];
        yield ['50b', 50];
        yield ['50KB', 51_200];
        yield ['50kb', 51_200];
        yield ['50Kb', 51_200];
        yield ['50MB', 52_428_800];
        yield ['50mb', 52_428_800];
        yield ['50GB', 53_687_091_200];
        yield ['50gb', 53_687_091_200];
    }
}
