<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Middleware;

use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Pipeline\Middleware\ClassRenameMiddleware;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\AliasProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\MessagePublished;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Pipeline\Middleware\ClassRenameMiddleware */
class ClassRenameMiddlewareTest extends TestCase
{
    public function testRename(): void
    {
        $middleware = new ClassRenameMiddleware([
            AliasProfileCreated::class => ProfileCreated::class,
        ]);

        $event = AliasProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de')
        )->recordNow(5);

        $bucket = new EventBucket(
            Profile::class,
            1,
            $event
        );

        $result = $middleware($bucket);

        self::assertCount(1, $result);

        $newEvent = $result[0]->event();

        self::assertInstanceOf(ProfileCreated::class, $newEvent);
        self::assertNotInstanceOf(AliasProfileCreated::class, $newEvent);
        self::assertEquals($event->payload(), $newEvent->payload());
        self::assertEquals($event->playhead(), $newEvent->playhead());
        self::assertEquals($event->recordedOn(), $newEvent->recordedOn());
        self::assertEquals($event->aggregateId(), $newEvent->aggregateId());
    }

    public function testSkip(): void
    {
        $middleware = new ClassRenameMiddleware([
            ProfileVisited::class => MessagePublished::class,
        ]);

        $bucket = new EventBucket(
            Profile::class,
            1,
            AliasProfileCreated::raise(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de')
            )->recordNow(5)
        );

        $result = $middleware($bucket);

        self::assertEquals([$bucket], $result);
    }
}
