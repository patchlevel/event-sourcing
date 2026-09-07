<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console;

use Patchlevel\EventSourcing\Console\InputHelper;
use Patchlevel\EventSourcing\Console\InvalidArgumentGiven;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InputHelper::class)]
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

    public function testValidIntFromInt(): void
    {
        self::assertSame(1, InputHelper::int(1));
    }

    public function testValidIntFromString(): void
    {
        self::assertSame(2, InputHelper::int('2'));
    }

    public function testInvalidIntFromBool(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "int" got "bool"');

        InputHelper::int(true);
    }

    public function testInvalidIntFromNonNumericString(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "int" got "string"');

        InputHelper::int('foo');
    }

    public function testValidPositiveInt(): void
    {
        self::assertSame(1, InputHelper::positiveInt(1));
        self::assertSame(2, InputHelper::positiveInt('2'));
    }

    public function testInvalidPositiveIntFromBool(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "positive-int" got "bool"');

        InputHelper::positiveInt(true);
    }

    public function testInvalidPositiveIntFromNonNumericString(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "positive-int" got "string"');

        InputHelper::positiveInt('foo');
    }

    public function testInvalidPositiveIntFromZero(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "positive-int" got "int"');

        InputHelper::positiveInt(0);
    }

    public function testValidNullablePositiveInt(): void
    {
        self::assertSame(1, InputHelper::nullablePositiveInt(1));
        self::assertSame(2, InputHelper::nullablePositiveInt('2'));
    }

    public function testValidNullablePositiveIntIsNull(): void
    {
        self::assertSame(null, InputHelper::nullablePositiveInt(null));
    }

    public function testInvalidNullablePositiveIntFromBool(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "positive-int|null" got "bool"');

        InputHelper::nullablePositiveInt(true);
    }

    public function testInvalidNullablePositiveIntFromNonNumericString(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "positive-int|null" got "string"');

        InputHelper::nullablePositiveInt('foo');
    }

    public function testInvalidNullablePositiveIntFromZero(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "positive-int|null" got "int"');

        InputHelper::nullablePositiveInt(0);
    }

    public function testValidPositiveIntOrZero(): void
    {
        self::assertSame(0, InputHelper::positiveIntOrZero(0));
        self::assertSame(5, InputHelper::positiveIntOrZero('5'));
    }

    public function testInvalidPositiveIntOrZeroFromBool(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "positive-int|0" got "bool"');

        InputHelper::positiveIntOrZero(true);
    }

    public function testInvalidPositiveIntOrZeroFromNonNumericString(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "positive-int|0" got "string"');

        InputHelper::positiveIntOrZero('foo');
    }

    public function testInvalidPositiveIntOrZeroFromNegativeInt(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "positive-int|0" got "int"');

        InputHelper::positiveIntOrZero(-1);
    }

    public function testValidNullableStringList(): void
    {
        self::assertSame(['foo', 'bar'], InputHelper::nullableStringList(['foo', 'bar']));
    }

    public function testValidNullableStringListIsNull(): void
    {
        self::assertSame(null, InputHelper::nullableStringList(null));
    }

    public function testValidNullableStringListFromEmptyArray(): void
    {
        self::assertSame(null, InputHelper::nullableStringList([]));
    }

    public function testInvalidNullableStringList(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "list<string>|null" got "string"');

        InputHelper::nullableStringList('foo');
    }

    public function testInvalidNullableStringListElement(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "list<string>|null" got "array"');

        InputHelper::nullableStringList([1]);
    }
}
