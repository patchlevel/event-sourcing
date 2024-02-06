<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Event;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Attribute\Event */
final class EventTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new Event('foo');

        self::assertSame('foo', $attribute->name);
    }
}
