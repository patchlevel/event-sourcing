<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Middleware;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Middleware\ExcludeArchivedEventMiddleware;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Message\Middleware\ExcludeArchivedEventMiddleware */
final class ExcludeArchivedEventMiddlewareTest extends TestCase
{
    public function testExcludedEvent(): void
    {
        $middleware = new ExcludeArchivedEventMiddleware();

        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        )->withHeader(new ArchivedHeader(true));

        $result = $middleware($message);

        self::assertSame([], $result);
    }

    public function testIncludeEvent(): void
    {
        $middleware = new ExcludeArchivedEventMiddleware();

        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        )->withHeader(new ArchivedHeader(false));

        $result = $middleware($message);

        self::assertSame([$message], $result);
    }

    public function testHeaderNotSet(): void
    {
        $middleware = new ExcludeArchivedEventMiddleware();

        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );

        $result = $middleware($message);

        self::assertSame([$message], $result);
    }
}
