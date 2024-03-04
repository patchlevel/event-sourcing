<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository\MessageDecorator;

use Patchlevel\EventSourcing\Repository\MessageDecorator\Trace;
use Patchlevel\EventSourcing\Repository\MessageDecorator\TraceStack;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Repository\MessageDecorator\TraceStack */
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
