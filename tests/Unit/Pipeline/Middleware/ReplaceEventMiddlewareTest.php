<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline;

use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Pipeline\Middleware\ReplaceEventMiddleware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

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
            ProfileCreated::raise(
                ProfileId::fromString('1'),
                Email::fromString('d.a.badura@gmail.com')
            )->recordNow(5)
        );

        $result = $middleware($bucket);

        self::assertCount(1, $result);
        self::assertEquals(Profile::class, $result[0]->aggregateClass());

        $event = $result[0]->event();

        self::assertInstanceOf(ProfileVisited::class, $event);
        self::assertEquals(5, $event->playhead());
    }
}
