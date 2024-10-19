<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Middleware;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Pipeline\Middleware\IncludeEventWithHeaderMiddleware;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Pipeline\Middleware\IncludeEventWithHeaderMiddleware */
final class IncludeEventWithHeaderMiddlewareTest extends TestCase
{
    public function testExcludedEvent(): void
    {
        $middleware = new IncludeEventWithHeaderMiddleware(ArchivedHeader::class);

        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );

        $result = $middleware($message);

        self::assertSame([], $result);
    }

    public function testIncludeEvent(): void
    {
        $middleware = new IncludeEventWithHeaderMiddleware(ArchivedHeader::class);

        $message = Message::create(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        )->withHeader(new ArchivedHeader());

        $result = $middleware($message);

        self::assertSame([$message], $result);
    }
}
