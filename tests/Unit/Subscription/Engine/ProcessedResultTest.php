<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ProcessedResult::class)]
final class ProcessedResultTest extends TestCase
{
    public function testInstantiate(): void
    {
        $object = new ProcessedResult(5, true, [$error = new Error('foo', 'bar', new RuntimeException('error'))]);

        self::assertSame(5, $object->processedMessages);
        self::assertTrue($object->finished);
        self::assertSame([$error], $object->errors);
    }

    public function testEmpty(): void
    {
        $result = ProcessedResult::empty();

        self::assertSame(0, $result->processedMessages);
        self::assertTrue($result->finished);
        self::assertSame([], $result->errors);
    }

    public function testMergeEmpty(): void
    {
        self::assertEquals(ProcessedResult::empty(), ProcessedResult::merge([]));
    }

    public function testMerge(): void
    {
        $error = new Error('foo', 'bar', new RuntimeException('error'));

        $result = ProcessedResult::merge([
            new ProcessedResult(2, true),
            new ProcessedResult(3, false, [$error]),
        ]);

        self::assertSame(5, $result->processedMessages);
        self::assertFalse($result->finished);
        self::assertSame([$error], $result->errors);
    }
}
