<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Middleware;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware */
class RecalculatePlayheadMiddlewareTest extends TestCase
{
    public function testReculatePlayhead(): void
    {
        $middleware = new RecalculatePlayheadMiddleware();

        $message = new Message(
            Profile::class,
            '1',
            5,
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de')
            )
        );

        $result = $middleware($message);

        self::assertCount(1, $result);
        self::assertSame(Profile::class, $result[0]->aggregateClass());
        self::assertSame(1, $result[0]->playhead());
    }

    public function testReculatePlayheadWithSamePlayhead(): void
    {
        $middleware = new RecalculatePlayheadMiddleware();

        $message = new Message(
            Profile::class,
            '1',
            1,
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de')
            )
        );

        $result = $middleware($message);

        self::assertSame([$message], $result);
    }
}
