<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Target;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Pipeline\Target\ProjectorTarget;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorResolver;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Pipeline\Target\ProjectorTarget */
final class ProjectorTargetTest extends TestCase
{
    use ProphecyTrait;

    public function testSave(): void
    {
        $message = new Message(
            new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@test.com')),
        );

        $projector = new class {
            public Message|null $message = null;

            public function __invoke(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveSubscribeMethod($projector, $message)->shouldBeCalledOnce()->willReturn($projector(...));

        $projectorTarget = new ProjectorTarget($projector, $projectorResolver->reveal());
        $projectorTarget->save($message);

        self::assertSame($message, $projector->message);
    }

    public function testSaveNoHit(): void
    {
        $message = new Message(
            new ProfileCreated(ProfileId::fromString('1'), Email::fromString('foo@test.com')),
        );

        $projector = new class {
            public Message|null $message = null;

            public function __invoke(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveSubscribeMethod($projector, $message)->shouldBeCalledOnce()->willReturn(null);

        $projectorTarget = new ProjectorTarget($projector, $projectorResolver->reveal());
        $projectorTarget->save($message);

        self::assertNull($projector->message);
    }
}
