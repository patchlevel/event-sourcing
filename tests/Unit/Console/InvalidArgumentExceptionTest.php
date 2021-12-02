<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console;

use Patchlevel\EventSourcing\Console\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InvalidArgumentExceptionTest extends TestCase
{
    public function testException(): void
    {
        $expectedValue = 'foo';
        $exception = new InvalidArgumentException($expectedValue, 'int');

        self::assertEquals('Invalid argument given: need type "int" got "string"', $exception->getMessage());
        self::assertEquals($expectedValue, $exception->value());
    }
}
