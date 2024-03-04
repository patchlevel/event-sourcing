<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Patchlevel\EventSourcing\Attribute\Header;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\NewStreamStartHeader;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Store\NewStreamStartHeader */
final class NewStreamStartHeaderTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new NewStreamStartHeader(true);

        self::assertTrue($attribute->newStreamStart);
    }
}
