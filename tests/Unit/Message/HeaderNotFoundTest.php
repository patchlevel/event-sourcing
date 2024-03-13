<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message;

use Patchlevel\EventSourcing\Message\HeaderNotFound;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Message\HeaderNotFound */
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
