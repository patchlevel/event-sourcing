<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Patchlevel\EventSourcing\Store\UniqueConstraintViolation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(UniqueConstraintViolation::class)]
final class UniqueConstraintViolationTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new UniqueConstraintViolation($previous = new RuntimeException('foo'));

        self::assertSame(
            'unique constraint violation',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }
}
