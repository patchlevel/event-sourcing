<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projection\Store;

use Patchlevel\EventSourcing\Projection\Projection\Store\ErrorSerializer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function serialize;

/** @covers \Patchlevel\EventSourcing\Projection\Projection\Store\ErrorSerializer */
final class ErrorSerializerTest extends TestCase
{
    public function testSerializeError(): void
    {
        $error = new RuntimeException('foo');

        self::assertEquals(serialize($error), ErrorSerializer::serialize($error));
    }

    public function testSerializeNull(): void
    {
        self::assertNull(ErrorSerializer::serialize(null));
    }

    public function testUnserializeError(): void
    {
        $error = new RuntimeException('foo');

        $serialized = serialize($error);

        self::assertEquals($error, ErrorSerializer::unserialize($serialized));
    }

    public function testUnserializeNull(): void
    {
        self::assertNull(ErrorSerializer::unserialize(null));
    }

    public function testUnserializeInvalid(): void
    {
        self::assertNull(ErrorSerializer::unserialize('foo'));
    }
}
