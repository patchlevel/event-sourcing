<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Message\Middleware;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Middleware\ReplaceEventMiddleware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\MessagePublished;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Message\Middleware\ReplaceEventMiddleware */
final class ReplaceEventMiddlewareTest extends TestCase
{
    public function testReplace(): void
    {
        $middleware = new ReplaceEventMiddleware(
            ProfileCreated::class,
            static function (ProfileCreated $event) {
                return new ProfileVisited(
                    $event->profileId,
                );
            },
        );

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );

        $result = $middleware($message);

        self::assertCount(1, $result);

        $event = $result[0]->event();

        self::assertInstanceOf(ProfileVisited::class, $event);
    }

    public function testReplaceInvalidClass(): void
    {
        /** @psalm-suppress InvalidArgument */
        $middleware = new ReplaceEventMiddleware(
            MessagePublished::class,
            static function (ProfileCreated $event) {
                return new ProfileVisited(
                    $event->profileId,
                );
            },
        );

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de'),
            ),
        );

        $result = $middleware($message);

        self::assertCount(1, $result);

        $event = $result[0]->event();

        self::assertInstanceOf(ProfileCreated::class, $event);
    }
}
