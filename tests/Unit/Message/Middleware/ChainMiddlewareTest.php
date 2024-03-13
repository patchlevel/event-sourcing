<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Middleware;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Middleware\ChainMiddleware;
use Patchlevel\EventSourcing\Message\Middleware\Middleware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Message\Middleware\ChainMiddleware */
final class ChainMiddlewareTest extends TestCase
{
    use ProphecyTrait;

    public function testChain(): void
    {
        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
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
