<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Debug\Trace;

use Patchlevel\EventSourcing\Debug\Trace\Trace;
use Patchlevel\EventSourcing\Debug\Trace\TraceStack;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Debug\Trace\TraceStack */
final class TraceStackTest extends TestCase
{
    use ProphecyTrait;

    public function testStack(): void
    {
        $stack = new TraceStack();

        self::assertEquals([], $stack->get());

        $trace = new Trace('name', 'category');

        $stack->add($trace);

        self::assertEquals([$trace], $stack->get());

        $stack->remove($trace);

        self::assertEquals([], $stack->get());
    }
}
