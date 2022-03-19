<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Middleware;

use Patchlevel\EventSourcing\EventBus\Message;
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

        $event = new AliasProfileCreated(
            ProfileId::fromString('1'),
            Email::fromString('hallo@patchlevel.de')
        );

        $message = new Message(
            Profile::class,
            '1',
            1,
            $event
        );

        $result = $middleware($message);

        self::assertCount(1, $result);

        $newMessage = $result[0];
        $newEvent = $newMessage->event();

        self::assertInstanceOf(ProfileCreated::class, $newEvent);
        self::assertNotInstanceOf(AliasProfileCreated::class, $newEvent);
        self::assertEquals($event->profileId, $newEvent->profileId);
        self::assertEquals($event->email, $newEvent->email);
        self::assertSame($message->playhead(), $newMessage->playhead());
        self::assertSame($message->recordedOn(), $newMessage->recordedOn());
        self::assertSame($message->aggregateId(), $newMessage->aggregateId());
        self::assertSame($message->aggregateClass(), $newMessage->aggregateClass());
    }

    public function testSkip(): void
    {
        $middleware = new ClassRenameMiddleware([
            ProfileVisited::class => MessagePublished::class,
        ]);

        $message = new Message(
            Profile::class,
            '1',
            1,
            new AliasProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hallo@patchlevel.de')
            )
        );

        $result = $middleware($message);

        self::assertSame([$message], $result);
    }
}
