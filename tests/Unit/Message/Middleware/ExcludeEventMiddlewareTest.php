<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Middleware;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Middleware\ExcludeEventMiddleware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Message\Middleware\ExcludeEventMiddleware */
final class ExcludeEventMiddlewareTest extends TestCase
{
    public function testDeleteEvent(): void
    {
        $middleware = new ExcludeEventMiddleware([ProfileCreated::class]);

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );

        $result = $middleware($message);

        self::assertSame([], $result);
    }

    public function testSkipEvent(): void
    {
        $middleware = new ExcludeEventMiddleware([ProfileCreated::class]);

        $message = new Message(
            new ProfileVisited(
                ProfileId::fromString('1'),
            ),
        );

        $result = $middleware($message);

        self::assertSame([$message], $result);
    }
}
