<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline;

use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

class RecalculatePlayheadMiddlewareTest extends TestCase
{
    public function testReculatePlayhead(): void
    {
        $middleware = new RecalculatePlayheadMiddleware();

        $bucket = new EventBucket(
            Profile::class,
            ProfileCreated::raise(
                ProfileId::fromString('1'),
                Email::fromString('d.a.badura@gmail.com')
            )->recordNow(5)
        );

        $result = $middleware($bucket);

        self::assertCount(1, $result);
        self::assertEquals(Profile::class, $result[0]->aggregateClass());

        $event = $result[0]->event();

        self::assertEquals(0, $event->playhead());
    }

    public function testReculatePlayheadWithSamePlayhead(): void
    {
        $middleware = new RecalculatePlayheadMiddleware();

        $bucket = new EventBucket(
            Profile::class,
            ProfileCreated::raise(
                ProfileId::fromString('1'),
                Email::fromString('d.a.badura@gmail.com')
            )->recordNow(0)
        );

        $result = $middleware($bucket);

        self::assertCount(1, $result);
        self::assertEquals(Profile::class, $result[0]->aggregateClass());

        $event = $result[0]->event();

        self::assertEquals(0, $event->playhead());
    }
}
