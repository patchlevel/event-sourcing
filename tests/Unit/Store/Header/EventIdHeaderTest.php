<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store\Header;

use Patchlevel\EventSourcing\Store\Header\EventIdHeader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventIdHeader::class)]
final class EventIdHeaderTest extends TestCase
{
    public function testInstantiate(): void
    {
        $object = new EventIdHeader('foo');

        self::assertSame('foo', $object->eventId);
    }
}
