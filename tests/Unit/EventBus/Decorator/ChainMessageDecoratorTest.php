<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\EventBus\Decorator;

use Patchlevel\EventSourcing\EventBus\Decorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\EventBus\Decorator\MessageDecorator;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\EventBus\Decorator\ChainMessageDecorator */
class ChainMessageDecoratorTest extends TestCase
{
    use ProphecyTrait;

    public function testChain(): void
    {
        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de')
            )
        );

        $decorator1 = $this->prophesize(MessageDecorator::class);
        $decorator1->__invoke($message)->willReturn($message)->shouldBeCalled();

        $decorator2 = $this->prophesize(MessageDecorator::class);
        $decorator2->__invoke($message)->willReturn($message)->shouldBeCalled();

        $chain = new ChainMessageDecorator([
            $decorator1->reveal(),
            $decorator2->reveal(),
        ]);

        $chain($message);
    }
}
