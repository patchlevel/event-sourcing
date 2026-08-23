<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Patchlevel\EventSourcing\Store\InvalidStreamName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidStreamName::class)]
final class InvalidStreamNameTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new InvalidStreamName('foo');

        self::assertSame(
            'Invalid stream name "foo"',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
