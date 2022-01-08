<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Middleware;

use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Pipeline\Middleware\FromIndexEventMiddleware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Pipeline\Middleware\FromIndexEventMiddleware */
class FromIndexEventMiddlewareTest extends TestCase
{
    public function testPositive(): void
    {
        $middleware = new FromIndexEventMiddleware(0);

        $bucket = new EventBucket(
            Profile::class,
            1,
            ProfileCreated::raise(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de')
            )->recordNow(0)
        );

        $result = $middleware($bucket);

        self::assertEquals([$bucket], $result);
    }

    public function testNegative(): void
    {
        $middleware = new FromIndexEventMiddleware(1);

        $bucket = new EventBucket(
            Profile::class,
            1,
            ProfileCreated::raise(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de')
            )->recordNow(0)
        );

        $result = $middleware($bucket);

        self::assertEquals([], $result);
    }
}
