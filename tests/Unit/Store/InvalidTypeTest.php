<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Patchlevel\EventSourcing\Store\InvalidType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidType::class)]
final class InvalidTypeTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new InvalidType('limit', 'int');

        self::assertSame(
            '"limit" should be a "int" type',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
