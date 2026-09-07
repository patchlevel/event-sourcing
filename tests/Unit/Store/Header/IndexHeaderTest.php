<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store\Header;

use Patchlevel\EventSourcing\Store\Header\IndexHeader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IndexHeader::class)]
final class IndexHeaderTest extends TestCase
{
    public function testInstantiate(): void
    {
        $object = new IndexHeader(42);

        self::assertSame(42, $object->index);
    }
}
