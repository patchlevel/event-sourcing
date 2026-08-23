<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use Patchlevel\EventSourcing\Aggregate\InvalidAggregateStreamName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidAggregateStreamName::class)]
final class InvalidAggregateStreamNameTest extends TestCase
{
    public function testCreate(): void
    {
        $exception = new InvalidAggregateStreamName('foo');

        self::assertSame(
            'Invalid aggregate stream name "foo". Expected format is "[aggregateName]-[aggregateId]".',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
    }
}
