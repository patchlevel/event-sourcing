<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Aggregate;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Attribute\Aggregate */
final class AggregateTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new Aggregate('foo');

        self::assertSame('foo', $attribute->name);
    }
}
