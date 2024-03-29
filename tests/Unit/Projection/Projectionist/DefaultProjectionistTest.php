<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projectionist;

use Generator;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCollection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionStatus;
use Patchlevel\EventSourcing\Projection\Projection\Store\ProjectionStore;
use Patchlevel\EventSourcing\Projection\Projectionist\DefaultProjectionist;
use Patchlevel\EventSourcing\Projection\Projectionist\VersionedProjector;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorRepository;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorResolver;
use Patchlevel\EventSourcing\Store\StreamableStore;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Tests\Unit\Projection\DummyStore;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use RuntimeException;

/** @covers \Patchlevel\EventSourcing\Projection\Projectionist\DefaultProjectionist */
final class DefaultProjectionistTest extends TestCase
{
    use ProphecyTrait;

    public function testNothingToBoot(): void
    {
        $projectionCollection = new ProjectionCollection();

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->shouldNotBeCalled();

        $projectionStore = $this->prophesize(ProjectionStore::class);
        $projectionStore->all()->willReturn($projectionCollection)->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore->reveal(),
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger(),
        );

        $projectionist->boot();
    }

    public function testBootWithoutCreateMethod(): void
    {
        $projector = new class implements VersionedProjector {
            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }
        };

        $projectionStore = new DummyStore([
            new Projection($projector->targetProjection()),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger(),
        );

        $projectionist->boot();

        self::assertEquals([
            new Projection($projector->targetProjection(), ProjectionStatus::Booting),
            new Projection($projector->targetProjection(), ProjectionStatus::Booting, 1),
            new Projection($projector->targetProjection(), ProjectionStatus::Active, 1),
        ], $projectionStore->savedProjections);
    }

    public function testBootWithMethods(): void
    {
        $projector = new class implements VersionedProjector {
            public Message|null $message = null;
            public bool $created = false;

            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }

            public function create(): void
            {
                $this->created = true;
            }

            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionStore = new DummyStore();

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveCreateMethod($projector)->willReturn($projector->create(...));
        $projectorResolver->resolveHandleMethod($projector, $message)->willReturn($projector->handle(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger(),
        );

        $projectionist->boot();

        self::assertEquals([
            new Projection($projector->targetProjection(), ProjectionStatus::Booting),
            new Projection($projector->targetProjection(), ProjectionStatus::Booting, 1),
            new Projection($projector->targetProjection(), ProjectionStatus::Active, 1),
        ], $projectionStore->savedProjections);

        self::assertTrue($projector->created);
        self::assertSame($message, $projector->message);
    }

    public function testBootWithLimit(): void
    {
        $projector = new class implements VersionedProjector {
            public Message|null $message = null;
            public bool $created = false;

            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }

            public function create(): void
            {
                $this->created = true;
            }

            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionStore = new DummyStore();

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveCreateMethod($projector)->willReturn($projector->create(...));
        $projectorResolver->resolveHandleMethod($projector, $message)->willReturn($projector->handle(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger(),
        );

        $projectionist->boot(new ProjectionCriteria(), 1);

        self::assertEquals([
            new Projection($projector->targetProjection(), ProjectionStatus::Booting),
            new Projection($projector->targetProjection(), ProjectionStatus::Booting, 1),
        ], $projectionStore->savedProjections);

        self::assertTrue($projector->created);
        self::assertSame($message, $projector->message);
    }

    public function testBootWithCreateError(): void
    {
        $projector = new class implements VersionedProjector {
            public Message|null $message = null;
            public bool $created = false;

            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }

            public function create(): void
            {
                throw new RuntimeException('ERROR');
            }
        };

        $projectionStore = new DummyStore([
            new Projection($projector->targetProjection()),
        ]);

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->shouldNotBeCalled();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveCreateMethod($projector)->willReturn($projector->create(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger(),
        );

        $projectionist->boot();

        self::assertEquals([
            new Projection($projector->targetProjection(), ProjectionStatus::Booting),
            new Projection($projector->targetProjection(), ProjectionStatus::Error),
        ], $projectionStore->savedProjections);
    }

    public function testRunning(): void
    {
        $projector = new class implements VersionedProjector {
            public Message|null $message = null;

            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }

            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionStore = new DummyStore([new Projection($projector->targetProjection(), ProjectionStatus::Active)]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveHandleMethod($projector, $message)->willReturn($projector->handle(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger(),
        );

        $projectionist->run();

        self::assertEquals([
            new Projection($projector->targetProjection(), ProjectionStatus::Active, 1),
        ], $projectionStore->savedProjections);

        self::assertSame($message, $projector->message);
    }

    public function testRunningWithLimit(): void
    {
        $projector = new class implements VersionedProjector {
            public Message|null $message = null;

            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }

            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionStore = new DummyStore([new Projection($projector->targetProjection(), ProjectionStatus::Active)]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));
        $message2 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message1, $message2): Generator {
            yield $message1;
            yield $message2;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveHandleMethod($projector, $message1)->willReturn($projector->handle(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger(),
        );

        $projectionist->run(new ProjectionCriteria(), 1);

        self::assertEquals([
            new Projection($projector->targetProjection(), ProjectionStatus::Active, 1),
        ], $projectionStore->savedProjections);

        self::assertSame($message1, $projector->message);
    }

    public function testRunningWithSkip(): void
    {
        $projector1 = new class implements VersionedProjector {
            public Message|null $message = null;

            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('test1', 1);
            }

            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projector2 = new class implements VersionedProjector {
            public Message|null $message = null;

            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('test2', 1);
            }

            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionStore = new DummyStore([
            new Projection($projector1->targetProjection(), ProjectionStatus::Active),
            new Projection($projector2->targetProjection(), ProjectionStatus::Active, 1),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector1, $projector2])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveHandleMethod($projector1, $message)->willReturn($projector1->handle(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger(),
        );

        $projectionist->run();

        self::assertEquals([
            new Projection($projector1->targetProjection(), ProjectionStatus::Active, 1),
        ], $projectionStore->savedProjections);

        self::assertSame($message, $projector1->message);
        self::assertNull($projector2->message);
    }

    public function testRunningWithError(): void
    {
        $projector = new class implements VersionedProjector {
            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }

            public function handle(Message $message): void
            {
                throw new RuntimeException('ERROR');
            }
        };

        $projectionStore = new DummyStore([new Projection($projector->targetProjection(), ProjectionStatus::Active)]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveHandleMethod($projector, $message)->willReturn($projector->handle(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger(),
        );

        $projectionist->run();

        self::assertEquals([
            new Projection($projector->targetProjection(), ProjectionStatus::Error, 0),
        ], $projectionStore->savedProjections);
    }

    public function testRunningMarkOutdated(): void
    {
        $projectorId = new ProjectionId('test', 1);

        $projectionStore = new DummyStore([new Projection($projectorId, ProjectionStatus::Active)]);

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->shouldNotBeCalled();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger(),
        );

        $projectionist->run();

        self::assertEquals([
            new Projection($projectorId, ProjectionStatus::Outdated, 0),
        ], $projectionStore->savedProjections);
    }

    public function testRunningWithoutActiveProjectors(): void
    {
        $projector = new class implements VersionedProjector {
            public Message|null $message = null;

            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }

            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionStore = new DummyStore([new Projection($projector->targetProjection(), ProjectionStatus::Booting)]);

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->shouldNotBeCalled();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger(),
        );

        $projectionist->run();

        self::assertEquals([], $projectionStore->savedProjections);
    }

    public function testTeardownWithProjector(): void
    {
        $projector = new class implements VersionedProjector {
            public Message|null $message = null;
            public bool $dropped = false;

            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }

            public function drop(): void
            {
                $this->dropped = true;
            }
        };

        $projectionStore = new DummyStore([new Projection($projector->targetProjection(), ProjectionStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveDropMethod($projector)->willReturn($projector->drop(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger(),
        );

        $projectionist->teardown();

        self::assertEquals([], $projectionStore->savedProjections);
        self::assertEquals([$projector->targetProjection()], $projectionStore->removedProjectionIds);
        self::assertTrue($projector->dropped);
    }

    public function testTeardownWithProjectorAndError(): void
    {
        $projector = new class implements VersionedProjector {
            public Message|null $message = null;
            public bool $dropped = false;

            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }

            public function drop(): void
            {
                throw new RuntimeException('ERROR');
            }
        };

        $projectionStore = new DummyStore([new Projection($projector->targetProjection(), ProjectionStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveDropMethod($projector)->willReturn($projector->drop(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger(),
        );

        $projectionist->teardown();

        self::assertEquals([], $projectionStore->savedProjections);
        self::assertEquals([], $projectionStore->removedProjectionIds);
    }

    public function testTeardownWithoutProjector(): void
    {
        $projectorId = new ProjectionId('test', 1);

        $projectionStore = new DummyStore([new Projection($projectorId, ProjectionStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger(),
        );

        $projectionist->teardown();

        self::assertEquals([], $projectionStore->savedProjections);
        self::assertEquals([], $projectionStore->removedProjectionIds);
    }

    public function testRemoveWithProjector(): void
    {
        $projector = new class implements VersionedProjector {
            public bool $dropped = false;

            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }

            public function drop(): void
            {
                $this->dropped = true;
            }
        };

        $projectionStore = new DummyStore([new Projection($projector->targetProjection(), ProjectionStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveDropMethod($projector)->willReturn($projector->drop(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger(),
        );

        $projectionist->remove();

        self::assertEquals([], $projectionStore->savedProjections);
        self::assertEquals([$projector->targetProjection()], $projectionStore->removedProjectionIds);
        self::assertTrue($projector->dropped);
    }

    public function testRemoveWithoutDropMethod(): void
    {
        $projector = new class implements VersionedProjector {
            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }
        };

        $projectionStore = new DummyStore([new Projection($projector->targetProjection(), ProjectionStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveDropMethod($projector)->willReturn(null);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger(),
        );

        $projectionist->remove();

        self::assertEquals([], $projectionStore->savedProjections);
        self::assertEquals([$projector->targetProjection()], $projectionStore->removedProjectionIds);
    }

    public function testRemoveWithProjectorAndError(): void
    {
        $projector = new class implements VersionedProjector {
            public bool $dropped = false;

            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }

            public function drop(): void
            {
                throw new RuntimeException('ERROR');
            }
        };

        $projectionStore = new DummyStore([new Projection($projector->targetProjection(), ProjectionStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveDropMethod($projector)->willReturn($projector->drop(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger(),
        );

        $projectionist->remove();

        self::assertEquals([], $projectionStore->savedProjections);
        self::assertEquals([$projector->targetProjection()], $projectionStore->removedProjectionIds);
    }

    public function testRemoveWithoutProjector(): void
    {
        $projectorId = new ProjectionId('test', 1);

        $projectionStore = new DummyStore([new Projection($projectorId, ProjectionStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger(),
        );

        $projectionist->remove();

        self::assertEquals([], $projectionStore->savedProjections);
        self::assertEquals([$projectorId], $projectionStore->removedProjectionIds);
    }

    public function testReactivate(): void
    {
        $projector = new class implements VersionedProjector {
            public function targetProjection(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }
        };

        $projectionStore = new DummyStore([new Projection($projector->targetProjection(), ProjectionStatus::Error)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger(),
        );

        $projectionist->reactivate();

        self::assertEquals([
            new Projection($projector->targetProjection(), ProjectionStatus::Active, 0),
        ], $projectionStore->savedProjections);
    }
}
