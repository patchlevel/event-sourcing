<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Repository\MessageDecorator;

use Patchlevel\EventSourcing\EventBus\HeaderNotFound;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Repository\MessageDecorator\Trace;
use Patchlevel\EventSourcing\Repository\MessageDecorator\TraceDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\TraceHeader;
use Patchlevel\EventSourcing\Repository\MessageDecorator\TraceStack;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use stdClass;

/** @covers \Patchlevel\EventSourcing\Repository\MessageDecorator\TraceDecorator */
final class TraceDecoratorTest extends TestCase
{
    use ProphecyTrait;

    public function testWithoutTrace(): void
    {
        $this->expectException(HeaderNotFound::class);

        $stack = new TraceStack();
        $decorator = new TraceDecorator($stack);

        $message = new Message(new stdClass());

        $decoratedMessage = $decorator($message);

        self::assertEquals($message, $decoratedMessage);

        $decoratedMessage->header(TraceHeader::class);
    }

    public function testWithTrace(): void
    {
        $stack = new TraceStack();
        $stack->add(new Trace('name', 'category'));

        $decorator = new TraceDecorator($stack);

        $message = new Message(new stdClass());

        $decoratedMessage = $decorator($message);

        self::assertEquals(
            new TraceHeader([
                [
                    'name' => 'name',
                    'category' => 'category',
                ],
            ]),
            $decoratedMessage->header(TraceHeader::class),
        );
    }
}
