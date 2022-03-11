<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Target;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Pipeline\Target\ProjectionTarget;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Pipeline\Target\ProjectionTarget */
class ProjectionTargetTest extends TestCase
{
    use ProphecyTrait;

    public function testSave(): void
    {
        $event = ProfileCreated::raise(ProfileId::fromString('1'), Email::fromString('foo@test.com'));

        $message = new Message(
            Profile::class,
            '1',
            1,
            $event
        );

        $projection = new class implements Projection {
            public static ?AggregateChanged $handledEvent = null;

            #[Handle(ProfileCreated::class)]
            public function handleProfileCreated(ProfileCreated $event): void
            {
                self::$handledEvent = $event;
            }
        };

        $projectionTarget = new ProjectionTarget($projection);

        $projectionTarget->save($message);

        self::assertSame($event, $projection::$handledEvent);
    }

    public function testPassMessage(): void
    {
        $event = ProfileCreated::raise(ProfileId::fromString('1'), Email::fromString('foo@test.com'));

        $message = new Message(
            Profile::class,
            '1',
            1,
            $event
        );

        $projection = new class implements Projection {
            public static ?Message $handledMessage = null;

            #[Handle(ProfileCreated::class)]
            public function handleProfileCreated(Message $message): void
            {
                self::$handledMessage = $message;
            }
        };

        $projectionTarget = new ProjectionTarget($projection);

        $projectionTarget->save($message);

        self::assertSame($message, $projection::$handledMessage);
    }
}
