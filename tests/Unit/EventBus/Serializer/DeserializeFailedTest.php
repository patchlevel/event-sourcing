<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus\Serializer;

use Patchlevel\EventSourcing\EventBus\Serializer\DeserializeFailed;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\EventBus\Serializer\DeserializeFailed */
final class DeserializeFailedTest extends TestCase
{
    public function testDecodeFailed(): void
    {
        $exception = DeserializeFailed::decodeFailed();

        self::assertSame(
            'Error while decoding message',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }

    public function testInvalidData(): void
    {
        $exception = DeserializeFailed::invalidData('foo');

        self::assertSame(
            'Invalid data: string',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
