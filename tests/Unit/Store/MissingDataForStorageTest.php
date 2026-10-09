<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Store;

use Patchlevel\EventSourcing\Store\MissingDataForStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(MissingDataForStorage::class)]
final class MissingDataForStorageTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new MissingDataForStorage('streamName', $previous = new RuntimeException('foo'));

        self::assertSame(
            'Cannot save because the following information is missing: "streamName"',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }
}
