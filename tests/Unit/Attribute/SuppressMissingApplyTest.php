<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\SuppressMissingApply;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SuppressMissingApply::class)]
final class SuppressMissingApplyTest extends TestCase
{
    public function testSuppressEvents(): void
    {
        $attribute = new SuppressMissingApply([ProfileCreated::class]);

        self::assertSame([ProfileCreated::class], $attribute->suppressEvents);
        self::assertSame(false, $attribute->suppressAll);
    }

    public function testSuppressAll(): void
    {
        $attribute = new SuppressMissingApply(SuppressMissingApply::ALL);

        self::assertSame([], $attribute->suppressEvents);
        self::assertSame(true, $attribute->suppressAll);
    }
}
