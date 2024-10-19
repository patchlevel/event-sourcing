<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Middleware;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Pipeline\Middleware\ClosureMiddleware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Pipeline\Middleware\ClosureMiddleware */
final class ClosureMiddlewareTest extends TestCase
{
    public function testClosure(): void
    {
        $middleware = new ClosureMiddleware(static function (Message $message): array {
            return [$message];
        });

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );

        $result = $middleware($message);

        self::assertSame([$message], $result);
    }
}
