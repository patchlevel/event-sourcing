<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Outbox;

use Patchlevel\EventSourcing\Attribute\Header;
use Patchlevel\EventSourcing\Outbox\OutboxHeader;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Outbox\OutboxHeader */
final class OutboxHeaderTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new OutboxHeader(12);

        self::assertSame(12, $attribute->id);
    }
}
