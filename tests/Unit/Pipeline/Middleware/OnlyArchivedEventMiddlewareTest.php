<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Middleware;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Pipeline\Middleware\OnlyArchivedEventMiddleware;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Pipeline\Middleware\OnlyArchivedEventMiddleware */
final class OnlyArchivedEventMiddlewareTest extends TestCase
{
    public function testExcludedEvent(): void
    {
        $middleware = new OnlyArchivedEventMiddleware();

        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        )->withHeader(new ArchivedHeader(false));

        $result = $middleware($message);

        self::assertSame([], $result);
    }

    public function testIncludeEvent(): void
    {
        $middleware = new OnlyArchivedEventMiddleware();

        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        )->withHeader(new ArchivedHeader(true));

        $result = $middleware($message);

        self::assertSame([$message], $result);
    }

    public function testHeaderNotSet(): void
    {
        $middleware = new OnlyArchivedEventMiddleware();

        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );

        $result = $middleware($message);

        self::assertSame([], $result);
    }
}
