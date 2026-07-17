<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\BatchFlush;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BatchFlush::class)]
final class BatchFlushTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new BatchFlush(100);

        self::assertSame(100, $attribute->afterMessages);
    }

    public function testInstantiateWithDefaults(): void
    {
        $attribute = new BatchFlush();

        self::assertNull($attribute->afterMessages);
    }
}
