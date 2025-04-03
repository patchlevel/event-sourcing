<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Attribute;

use Patchlevel\EventSourcing\Attribute\Header;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Header::class)]
final class HeaderTest extends TestCase
{
    public function testInstantiate(): void
    {
        $attribute = new Header('foo');

        self::assertSame('foo', $attribute->name);
    }
}
