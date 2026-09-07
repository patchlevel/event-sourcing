<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Patchlevel\EventSourcing\Store\StoreIsReadOnly;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StoreIsReadOnly::class)]
final class StoreIsReadOnlyTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new StoreIsReadOnly();

        self::assertSame(
            'Store is in read only mode',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
