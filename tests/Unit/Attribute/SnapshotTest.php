<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Snapshot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Snapshot::class)]
final class SnapshotTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new Snapshot('foo');

        self::assertSame('foo', $attribute->name);
        self::assertNull($attribute->batch);
        self::assertNull($attribute->version);
    }
}
