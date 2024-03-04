<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Aggregate\AggregateHeader */
final class AggregateHeaderTest extends TestCase
{
    public function testInstantiate(): void
    {
        $recordedOn = new DateTimeImmutable();
        $attribute = new AggregateHeader('foo', '1', 1, $recordedOn);

        self::assertSame('foo', $attribute->aggregateName);
        self::assertSame('1', $attribute->aggregateId);
        self::assertSame(1, $attribute->playhead);
        self::assertSame($recordedOn, $attribute->recordedOn);
    }
}
