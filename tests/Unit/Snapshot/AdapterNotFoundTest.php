<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Snapshot;

use Patchlevel\EventSourcing\Snapshot\AdapterNotFound;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdapterNotFound::class)]
final class AdapterNotFoundTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new AdapterNotFound('foo');

        self::assertSame(
            'adapter with the name "foo" not found',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
