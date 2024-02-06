<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Pipeline\Target;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Pipeline\Target\ProjectorRepositoryTarget;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorRepository;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorResolver;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Pipeline\Target\ProjectorRepositoryTarget */
final class ProjectorRepositoryTargetTest extends TestCase
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

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->shouldBeCalledOnce()->willReturn([$projector]);

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveSubscribeMethod($projector, $message)->shouldBeCalledOnce()->willReturn($projector(...));

        $projectorRepositoryTarget = new ProjectorRepositoryTarget($projectorRepository->reveal(), $projectorResolver->reveal());
        $projectorRepositoryTarget->save($message);

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

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->shouldBeCalledOnce()->willReturn([$projector]);

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveSubscribeMethod($projector, $message)->shouldBeCalledOnce()->willReturn(null);

        $projectorRepositoryTarget = new ProjectorRepositoryTarget($projectorRepository->reveal(), $projectorResolver->reveal());
        $projectorRepositoryTarget->save($message);

        self::assertNull($projector->message);
    }
}
