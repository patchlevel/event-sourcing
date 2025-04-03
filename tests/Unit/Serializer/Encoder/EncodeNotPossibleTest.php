<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Serializer\Encoder;

use Patchlevel\EventSourcing\Serializer\Encoder\EncodeNotPossible;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EncodeNotPossible::class)]
final class EncodeNotPossibleTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new EncodeNotPossible([]);

        self::assertSame(
            'serialization is not possible',
            $exception->getMessage(),
        );
        self::assertSame([], $exception->data());
        self::assertSame(0, $exception->getCode());
    }
}
