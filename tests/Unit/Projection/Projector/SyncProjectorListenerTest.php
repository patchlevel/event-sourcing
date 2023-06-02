<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projector;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projector\Projector;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorRepository;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorResolver;
use Patchlevel\EventSourcing\Projection\Projector\SyncProjectorListener;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Projection\Projector\SyncProjectorListener */
final class SyncProjectorListenerTest extends TestCase
{
    use ProphecyTrait;

    public function testMethodHandle(): void
    {
        $projector = new class implements Projector {
            public Message|null $message = null;

            public function handleProfileCreated(Message $message): void
            {
                $this->message = $message;
            }
        };

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('foo@bar.com'),
            ),
        );

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();

        $resolver = $this->prophesize(ProjectorResolver::class);
        $resolver->resolveHandleMethod($projector, $message)->willReturn($projector->handleProfileCreated(...))->shouldBeCalledOnce();

        $projectionListener = new SyncProjectorListener(
            $projectorRepository->reveal(),
            $resolver->reveal(),
        );

        $projectionListener($message);

        self::assertSame($message, $projector->message);
    }

    public function testNoMethod(): void
    {
        $projector = new class implements Projector {
            public Message|null $message = null;

            public function handleProfileCreated(Message $message): void
            {
                $this->message = $message;
            }
        };

        $message = new Message(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('foo@bar.com'),
            ),
        );

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();

        $resolver = $this->prophesize(ProjectorResolver::class);
        $resolver->resolveHandleMethod($projector, $message)->willReturn(null)->shouldBeCalledOnce();

        $projectionListener = new SyncProjectorListener(
            $projectorRepository->reveal(),
            $resolver->reveal(),
        );

        $projectionListener($message);

        self::assertNull($projector->message);
    }
}
