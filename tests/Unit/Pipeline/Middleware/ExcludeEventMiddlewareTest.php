<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Middleware;

use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware */
class ExcludeEventMiddlewareTest extends TestCase
{
    public function testDeleteEvent(): void
    {
        $middleware = new ExcludeEventMiddleware([ProfileCreated::class]);

        $bucket = new EventBucket(
            Profile::class,
            1,
            ProfileCreated::raise(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de')
            )->recordNow(0)
        );

        $result = $middleware($bucket);

        self::assertSame([], $result);
    }

    public function testSkipEvent(): void
    {
        $middleware = new ExcludeEventMiddleware([ProfileCreated::class]);

        $bucket = new EventBucket(
            Profile::class,
            1,
            ProfileVisited::raise(
                ProfileId::fromString('1'),
                ProfileId::fromString('2')
            )->recordNow(0)
        );

        $result = $middleware($bucket);

        self::assertSame([$bucket], $result);
    }
}
