<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Attribute\SuppressMissingApply;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\TestCase;

class SuppressMissingApplyTest extends TestCase
{
    public function testSuppressEvents(): void
    {
        $attribute = new SuppressMissingApply([ProfileCreated::class]);

        self::assertEquals([ProfileCreated::class], $attribute->suppressEvents());
        self::assertEquals(false, $attribute->suppressAll());
    }

    public function testSuppressAll(): void
    {
        $attribute = new SuppressMissingApply('*');

        self::assertEquals([], $attribute->suppressEvents());
        self::assertEquals(true, $attribute->suppressAll());
    }

    public function testInvalidString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SuppressMissingApply('foo');
    }
}
