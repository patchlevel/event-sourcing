<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer;

use Patchlevel\EventSourcing\Serializer\SerializedEvent;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Serializer\SerializedEvent */
final class SerializedEventTest extends TestCase
{
    public function testCreate(): void
    {
        $event = new SerializedEvent('foo', 'bar');

        self::assertSame('foo', $event->name);
        self::assertSame('bar', $event->payload);
    }
}
