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
        self::assertEquals('foo', InputHelper::string('foo'));
    }

    public function testInvalidString(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "string" got "integer"');

        InputHelper::string(1);
    }

    public function testValidNullableString(): void
    {
        self::assertEquals('foo', InputHelper::nullableString('foo'));
    }

    public function testValidNullableStringIsNull(): void
    {
        self::assertEquals(null, InputHelper::nullableString(null));
    }

    public function testInvalidNullableString(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "string|null" got "integer"');

        InputHelper::nullableString(1);
    }

    public function testValidBoolean(): void
    {
        self::assertEquals(true, InputHelper::bool(true));
    }

    public function testInvalidBoolean(): void
    {
        $this->expectException(InvalidArgumentGiven::class);
        $this->expectExceptionMessage('Invalid argument given: need type "boolean" got "integer"');

        InputHelper::bool(1);
    }
}
