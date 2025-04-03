<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message;

use Patchlevel\EventSourcing\Message\HeaderNotFound;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HeaderNotFound::class)]
final class HeaderNotFoundTest extends TestCase
{
    public function testNotFound(): void
    {
        self::assertSame(
            'message header "foo" is not defined',
            (new HeaderNotFound('foo'))->getMessage(),
        );
    }
}
