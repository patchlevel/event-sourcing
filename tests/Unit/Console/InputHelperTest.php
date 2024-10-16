<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\InvalidArgumentGiven;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Console\InputHelper */
final class InputHelperTest extends TestCase
{
    public function testValidString(): void
    {
        self::assertSame('foo', InputHelper::string('foo'));
    }

    public function testInvalidString(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "string" got "int"');

        InputHelper::string(1);
    }

    public function testValidNullableString(): void
    {
        self::assertSame('foo', InputHelper::nullableString('foo'));
    }

    public function testValidNullableStringIsNull(): void
    {
        self::assertSame(null, InputHelper::nullableString(null));
    }

    public function testInvalidNullableString(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "string|null" got "int"');

        InputHelper::nullableString(1);
    }

    public function testValidBoolean(): void
    {
        self::assertSame(true, InputHelper::bool(true));
    }

    public function testInvalidBoolean(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "bool" got "int"');

        InputHelper::bool(1);
    }

    public function testValidInt(): void
    {
        self::assertSame(1, InputHelper::nullableInt(1));
    }

    public function testValidNullInt(): void
    {
        self::assertSame(null, InputHelper::nullableInt(null));
    }

    public function testInvalidInt(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "int|null" got "bool"');

        InputHelper::nullableInt(true);
    }

    public function testInvalidIntAsString(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "int|null" got "string"');

        InputHelper::nullableInt('foo');
    }
}
