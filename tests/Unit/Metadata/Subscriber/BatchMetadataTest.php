<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\Subscriber\BatchMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BatchMetadata::class)]
final class BatchMetadataTest extends TestCase
{
    public function testInstantiate(): void
    {
        $metadata = new BatchMetadata('flush', 'begin', 'shouldFlush', 'rollback', 100);

        self::assertSame('flush', $metadata->flushMethod);
        self::assertSame('begin', $metadata->beginMethod);
        self::assertSame('shouldFlush', $metadata->shouldFlushMethod);
        self::assertSame('rollback', $metadata->rollbackMethod);
        self::assertSame(100, $metadata->afterMessages);
    }

    public function testInstantiateWithDefaults(): void
    {
        $metadata = new BatchMetadata('flush');

        self::assertSame('flush', $metadata->flushMethod);
        self::assertNull($metadata->beginMethod);
        self::assertNull($metadata->shouldFlushMethod);
        self::assertNull($metadata->rollbackMethod);
        self::assertNull($metadata->afterMessages);
    }
}
