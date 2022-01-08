<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Attribute\SuppressMissingApply;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Attribute\SuppressMissingApply */
class SuppressMissingApplyTest extends TestCase
{
    public function testSuppressEvents(): void
    {
        $attribute = new SuppressMissingApply([ProfileCreated::class]);

        self::assertSame([ProfileCreated::class], $attribute->suppressEvents());
        self::assertSame(false, $attribute->suppressAll());
    }

    public function testSuppressAll(): void
    {
        $attribute = new SuppressMissingApply('*');

        self::assertSame([], $attribute->suppressEvents());
        self::assertSame(true, $attribute->suppressAll());
    }

    public function testInvalidString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SuppressMissingApply('foo');
    }
}
