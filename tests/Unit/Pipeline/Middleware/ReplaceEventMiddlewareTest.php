<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Middleware;

use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Pipeline\Middleware\ReplaceEventMiddleware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\MessagePublished;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Pipeline\Middleware\ReplaceEventMiddleware */
class ReplaceEventMiddlewareTest extends TestCase
{
    public function testReplace(): void
    {
        $middleware = new ReplaceEventMiddleware(
            ProfileCreated::class,
            static function (ProfileCreated $event) {
                return ProfileVisited::raise(
                    $event->profileId(),
                    $event->profileId()
                );
            }
        );

        $bucket = new EventBucket(
            Profile::class,
            1,
            ProfileCreated::raise(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de')
            )->recordNow(5)
        );

        $result = $middleware($bucket);

        self::assertCount(1, $result);
        self::assertEquals(Profile::class, $result[0]->aggregateClass());

        $event = $result[0]->event();

        self::assertInstanceOf(ProfileVisited::class, $event);
        self::assertEquals(5, $event->playhead());
    }

    public function testReplaceInvalidClass(): void
    {
        /** @psalm-suppress InvalidArgument */
        $middleware = new ReplaceEventMiddleware(
            MessagePublished::class,
            static function (ProfileCreated $event) {
                return ProfileVisited::raise(
                    $event->profileId(),
                    $event->profileId()
                );
            }
        );

        $bucket = new EventBucket(
            Profile::class,
            1,
            ProfileCreated::raise(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de')
            )->recordNow(5)
        );

        $result = $middleware($bucket);

        self::assertCount(1, $result);
        self::assertEquals(Profile::class, $result[0]->aggregateClass());

        $event = $result[0]->event();

        self::assertInstanceOf(ProfileCreated::class, $event);
        self::assertEquals(5, $event->playhead());
    }
}
