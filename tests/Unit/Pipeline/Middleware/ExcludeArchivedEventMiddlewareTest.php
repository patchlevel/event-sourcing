<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Middleware;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeArchivedEventMiddleware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeArchivedEventMiddleware */
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
        )->withArchived(true);

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
        )->withArchived(false);

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
