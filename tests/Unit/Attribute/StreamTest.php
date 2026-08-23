<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Stream::class)]
final class StreamTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new Stream('foo');

        self::assertSame('foo', $attribute->name);
    }
}
