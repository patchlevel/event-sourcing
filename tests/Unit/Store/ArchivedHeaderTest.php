<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Patchlevel\EventSourcing\Store\ArchivedHeader;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Store\ArchivedHeader */
final class ArchivedHeaderTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new ArchivedHeader(true);

        self::assertTrue($attribute->archived);
    }
}
