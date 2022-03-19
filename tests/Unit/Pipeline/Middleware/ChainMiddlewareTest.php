<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Middleware;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Pipeline\Middleware\ChainMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\Middleware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Pipeline\Middleware\ChainMiddleware */
class ChainMiddlewareTest extends TestCase
{
    use ProphecyTrait;

    public function testChain(): void
    {
        $event = ProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de')
        );

        $message = new Message(
            Profile::class,
            '1',
            1,
            $event
        );

        $child1 = $this->prophesize(Middleware::class);
        $child1->__invoke($message)->willReturn([$message])->shouldBeCalled();

        $child2 = $this->prophesize(Middleware::class);
        $child2->__invoke($message)->willReturn([$message])->shouldBeCalled();

        $middleware = new ChainMiddleware([
            $child1->reveal(),
            $child2->reveal(),
        ]);

        $middleware($message);
    }
}
