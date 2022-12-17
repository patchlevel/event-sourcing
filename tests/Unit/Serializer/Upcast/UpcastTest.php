<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer\Upcast;

use Patchlevel\EventSourcing\Serializer\Upcast\Upcast;
use PHPUnit\Framework\TestCase;

/**
 * @covers Patchlevel\EventSourcing\Serializer\Upcast\Upcast
 */
final class UpcastTest extends TestCase
{
    public function testReplaceEventName(): void
    {
        $upcast = new Upcast('foo', ['name' => 'max']);

        $newUpcast = $upcast->replaceEventName('bar');

        $this->assertNotSame($upcast, $newUpcast);
        $this->assertEquals('bar', $newUpcast->eventName);
        $this->assertEquals(['name' => 'max'], $newUpcast->payload);
    }

    public function testReplacePayload(): void
    {
        $upcast = new Upcast('foo', ['name' => 'max']);

        $newUpcast = $upcast->replacePayload(['name' => 'maxim']);

        $this->assertNotSame($upcast, $newUpcast);
        $this->assertEquals('foo', $newUpcast->eventName);
        $this->assertEquals(['name' => 'maxim'], $newUpcast->payload);
    }

    public function testReplacePayloadByKey(): void
    {
        $upcast = new Upcast('foo', ['name' => 'max']);

        $newUpcast = $upcast->replacePayloadByKey('name', 'maxim');

        $this->assertNotSame($upcast, $newUpcast);
        $this->assertEquals('foo', $newUpcast->eventName);
        $this->assertEquals(['name' => 'maxim'], $newUpcast->payload);
    }

    public function testReplacePayloadByKeyWithoutExistingKey(): void
    {
        $upcast = new Upcast('foo', ['name' => 'max']);

        $newUpcast = $upcast->replacePayloadByKey('age', 20);

        $this->assertNotSame($upcast, $newUpcast);
        $this->assertEquals('foo', $newUpcast->eventName);
        $this->assertEquals(['name' => 'max', 'age' => 20], $newUpcast->payload);
    }
}
