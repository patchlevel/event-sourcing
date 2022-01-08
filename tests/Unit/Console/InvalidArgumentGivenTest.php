<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console;

use Patchlevel\EventSourcing\Console\InvalidArgumentGiven;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Console\InvalidArgumentGiven */
final class InvalidArgumentGivenTest extends TestCase
{
    public function testException(): void
    {
        $expectedValue = 'foo';
        $exception = new InvalidArgumentGiven($expectedValue, 'int');

        self::assertSame('Invalid argument given: need type "int" got "string"', $exception->getMessage());
        self::assertSame($expectedValue, $exception->value());
    }
}
