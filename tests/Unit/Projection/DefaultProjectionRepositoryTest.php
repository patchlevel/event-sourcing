<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Projection\DefaultProjectionRepository;
use Patchlevel\EventSourcing\Projection\MethodDoesNotExist;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

final class DefaultProjectionRepositoryTest extends TestCase
{
    use ProphecyTrait;

    public function testHandleWithNoProjections(): void
    {
        $projectionRepository = new DefaultProjectionRepository([]);
        $projectionRepository->handle(ProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('profile@test.com')
        ));

        $this->expectNotToPerformAssertions();
    }

    public function testHandle(): void
    {
        $projection = new class implements Projection {
            public static ?AggregateChanged $handledEvent = null;

            /** @return iterable<class-string<AggregateChanged>, string> */
            public function handledEvents(): iterable
            {
                yield ProfileCreated::class => 'applyProfileCreated';
            }

            public function applyProfileCreated(ProfileCreated $event): void
            {
                self::$handledEvent = $event;
            }

            public function create(): void
            {
            }

            public function drop(): void
            {
            }
        };

        $profileCreated = ProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('profile@test.com')
        );

        $projectionRepository = new DefaultProjectionRepository([$projection]);
        $projectionRepository->handle($profileCreated);

        self::assertSame($profileCreated, $projection::$handledEvent);
    }

    public function testHandleNotSupportedEvent(): void
    {
        $projection = new class implements Projection {
            public static ?AggregateChanged $handledEvent = null;

            /** @return iterable<class-string<AggregateChanged>, string> */
            public function handledEvents(): iterable
            {
                yield ProfileCreated::class => 'applyProfileCreated';
            }

            public function applyProfileCreated(ProfileCreated $event): void
            {
                self::$handledEvent = $event;
            }

            public function create(): void
            {
            }

            public function drop(): void
            {
            }
        };

        $profileVisited = ProfileVisited::raise(
            ProfileId::fromString('1'),
            ProfileId::fromString('2'),
        );

        $projectionRepository = new DefaultProjectionRepository([$projection]);
        $projectionRepository->handle($profileVisited);

        self::assertNull($projection::$handledEvent);
    }

    public function testHandleButProjectionsMethodIsMissing(): void
    {
        $projection = new class implements Projection {
            /** @return iterable<class-string<AggregateChanged>, string> */
            public function handledEvents(): iterable
            {
                yield ProfileCreated::class => 'applyProfileCreated';
            }

            public function create(): void
            {
            }

            public function drop(): void
            {
            }
        };

        $profileCreated = ProfileCreated::raise(
            ProfileId::fromString('1'),
            Email::fromString('profile@test.com')
        );

        $projectionRepository = new DefaultProjectionRepository([$projection]);

        $this->expectException(MethodDoesNotExist::class);
        $projectionRepository->handle($profileCreated);
    }

    public function testCreate(): void
    {
        $projection = $this->prophesize(Projection::class);
        $projection->create()->shouldBeCalledOnce();

        $projectionRepository = new DefaultProjectionRepository([$projection->reveal()]);
        $projectionRepository->create();
    }

    public function testDrop(): void
    {
        $projection = $this->prophesize(Projection::class);
        $projection->drop()->shouldBeCalledOnce();

        $projectionRepository = new DefaultProjectionRepository([$projection->reveal()]);
        $projectionRepository->drop();
    }

    public function testDropWithNoProjections(): void
    {
        $projectionRepository = new DefaultProjectionRepository([]);
        $projectionRepository->drop();

        $this->expectNotToPerformAssertions();
    }
}
