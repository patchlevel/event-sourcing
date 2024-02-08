<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus;

use Patchlevel\EventSourcing\EventBus\HeaderNotFound;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\EventBus\HeaderNotFound */
final class HeaderNotFoundTest extends TestCase
{
    public function testAggregateName(): void
    {
        self::assertSame(
            'message header "aggregateName" is not defined',
            HeaderNotFound::aggregateName()->getMessage(),
        );
    }

    public function testAggregateId(): void
    {
        self::assertSame(
            'message header "aggregateId" is not defined',
            HeaderNotFound::aggregateId()->getMessage(),
        );
    }

    public function testPlayhead(): void
    {
        self::assertSame(
            'message header "playhead" is not defined',
            HeaderNotFound::playhead()->getMessage(),
        );
    }

    public function testRecordedOn(): void
    {
        self::assertSame(
            'message header "recordedOn" is not defined',
            HeaderNotFound::recordedOn()->getMessage(),
        );
    }

    public function testArchived(): void
    {
        self::assertSame(
            'message header "archived" is not defined',
            HeaderNotFound::archived()->getMessage(),
        );
    }

    public function testNewStreamStart(): void
    {
        self::assertSame(
            'message header "newStreamStart" is not defined',
            HeaderNotFound::newStreamStart()->getMessage(),
        );
    }

    public function testCustom(): void
    {
        self::assertSame(
            'message header "foo" is not defined',
            HeaderNotFound::custom('foo')->getMessage(),
        );
    }
}
