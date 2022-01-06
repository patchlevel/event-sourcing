<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Attribute\Suppress;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\TestCase;

class SuppressTest extends TestCase
{
    public function testSuppressEvents(): void
    {
        $attribute = new Suppress([ProfileCreated::class]);

        self::assertEquals([ProfileCreated::class], $attribute->suppressEvents());
        self::assertEquals(false, $attribute->suppressAll());
    }

    public function testSuppressAll(): void
    {
        $attribute = new Suppress('*');

        self::assertEquals([], $attribute->suppressEvents());
        self::assertEquals(true, $attribute->suppressAll());
    }

    public function testInvalidString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Suppress('foo');
    }
}
