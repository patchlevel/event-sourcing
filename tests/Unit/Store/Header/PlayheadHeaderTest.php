<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store\Header;

use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlayheadHeader::class)]
final class PlayheadHeaderTest extends TestCase
{
    public function testInstantiate(): void
    {
        $object = new PlayheadHeader(1);

        self::assertSame(1, $object->playhead);
    }
}
