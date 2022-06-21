<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Middleware;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Pipeline\Middleware\IncludeEventMiddleware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Pipeline\Middleware\IncludeEventMiddleware */
class IncludeEventMiddlewareTest extends TestCase
{
    public function testFilterEvent(): void
    {
        $middleware = new IncludeEventMiddleware([ProfileCreated::class]);

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de')
            )
        );

        $result = $middleware($message);

        self::assertSame([$message], $result);
    }

    public function testSkipEvent(): void
    {
        $middleware = new IncludeEventMiddleware([ProfileCreated::class]);

        $message = new Message(
            new ProfileVisited(
                ProfileId::fromString('1')
            )
        );

        $result = $middleware($message);

        self::assertSame([], $result);
    }
}
