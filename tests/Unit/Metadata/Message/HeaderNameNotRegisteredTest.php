<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Message;

use Patchlevel\EventSourcing\Metadata\Message\HeaderNameNotRegistered;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HeaderNameNotRegistered::class)]
final class HeaderNameNotRegisteredTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new HeaderNameNotRegistered('foo');

        self::assertSame(
            'Header name "foo" is not registered',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
