<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Header;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Attribute\Header */
final class HeaderTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new Header('foo');

        self::assertSame('foo', $attribute->name);
    }
}
