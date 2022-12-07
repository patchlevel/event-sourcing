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
use Patchlevel\EventSourcing\Projection\Projector\ProjectorRepository;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorResolver;
use Patchlevel\EventSourcing\Projection\Projector\StatefulProjector;
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
        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectionStore = $this->prophesize(ProjectionStore::class);
        $projectionStore->all()->willReturn($projectionCollection)->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->statefulProjectors()->willReturn([])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore->reveal(),
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->boot();
    }

    public function testBootWithoutCreateMethod(): void
    {
        $projector = new class implements StatefulProjector {
            public function projectionId(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }
        };

        $projectionStore = new DummyStore([
            new Projection($projector->projectionId()),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->statefulProjectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectionId($projector->projectionId())->willReturn($projector)->shouldBeCalledTimes(
            2
        );

        $projectorResolver = $this->prophesize(ProjectorResolver::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->boot();

        self::assertEquals([
            new Projection($projector->projectionId(), ProjectionStatus::Booting),
            new Projection($projector->projectionId(), ProjectionStatus::Booting, 1),
            new Projection($projector->projectionId(), ProjectionStatus::Active, 1),
        ], $projectionStore->savedStates);
    }

    public function testBootWithMethods(): void
    {
        $projector = new class implements StatefulProjector {
            public ?Message $message = null;
            public bool $created = false;

            public function projectionId(): ProjectionId
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
        $projectorRepository->statefulProjectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectionId($projector->projectionId())->willReturn($projector)->shouldBeCalledTimes(
            2
        );

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveCreateMethod($projector)->willReturn($projector->create(...));
        $projectorResolver->resolveHandleMethod($projector, $message)->willReturn($projector->handle(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->boot();

        self::assertEquals([
            new Projection($projector->projectionId(), ProjectionStatus::Booting),
            new Projection($projector->projectionId(), ProjectionStatus::Booting, 1),
            new Projection($projector->projectionId(), ProjectionStatus::Active, 1),
        ], $projectionStore->savedStates);

        self::assertTrue($projector->created);
        self::assertSame($message, $projector->message);
    }

    public function testBootWithLimit(): void
    {
        $projector = new class implements StatefulProjector {
            public ?Message $message = null;
            public bool $created = false;

            public function projectionId(): ProjectionId
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
        $projectorRepository->statefulProjectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectionId($projector->projectionId())->willReturn($projector)->shouldBeCalledTimes(
            2
        );

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveCreateMethod($projector)->willReturn($projector->create(...));
        $projectorResolver->resolveHandleMethod($projector, $message)->willReturn($projector->handle(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->boot(new ProjectionCriteria(), 1);

        self::assertEquals([
            new Projection($projector->projectionId(), ProjectionStatus::Booting),
            new Projection($projector->projectionId(), ProjectionStatus::Booting, 1),
        ], $projectionStore->savedStates);

        self::assertTrue($projector->created);
        self::assertSame($message, $projector->message);
    }

    public function testBootWithCreateError(): void
    {
        $projector = new class implements StatefulProjector {
            public ?Message $message = null;
            public bool $created = false;

            public function projectionId(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }

            public function create(): void
            {
                throw new RuntimeException('ERROR');
            }
        };

        $projectionStore = new DummyStore([
            new Projection($projector->projectionId()),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->statefulProjectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectionId($projector->projectionId())->willReturn($projector)->shouldBeCalledTimes(
            1
        );

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveCreateMethod($projector)->willReturn($projector->create(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->boot();

        self::assertEquals([
            new Projection($projector->projectionId(), ProjectionStatus::Booting),
            new Projection($projector->projectionId(), ProjectionStatus::Error),
        ], $projectionStore->savedStates);
    }

    public function testRunning(): void
    {
        $projector = new class implements StatefulProjector {
            public ?Message $message = null;

            public function projectionId(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }

            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionStore = new DummyStore([new Projection($projector->projectionId(), ProjectionStatus::Active)]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->statefulProjectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectionId($projector->projectionId())->willReturn($projector)->shouldBeCalledTimes(
            2
        );

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveHandleMethod($projector, $message)->willReturn($projector->handle(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->run();

        self::assertEquals([
            new Projection($projector->projectionId(), ProjectionStatus::Active, 1),
        ], $projectionStore->savedStates);

        self::assertSame($message, $projector->message);
    }

    public function testRunningWithLimit(): void
    {
        $projector = new class implements StatefulProjector {
            public ?Message $message = null;

            public function projectionId(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }

            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionStore = new DummyStore([new Projection($projector->projectionId(), ProjectionStatus::Active)]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));
        $message2 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message1, $message2): Generator {
            yield $message1;
            yield $message2;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->statefulProjectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectionId($projector->projectionId())->willReturn($projector)->shouldBeCalledTimes(
            2
        );

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveHandleMethod($projector, $message1)->willReturn($projector->handle(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->run(new ProjectionCriteria(), 1);

        self::assertEquals([
            new Projection($projector->projectionId(), ProjectionStatus::Active, 1),
        ], $projectionStore->savedStates);

        self::assertSame($message1, $projector->message);
    }

    public function testRunningWithSkip(): void
    {
        $projector1 = new class implements StatefulProjector {
            public ?Message $message = null;

            public function projectionId(): ProjectionId
            {
                return new ProjectionId('test1', 1);
            }

            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projector2 = new class implements StatefulProjector {
            public ?Message $message = null;

            public function projectionId(): ProjectionId
            {
                return new ProjectionId('test2', 1);
            }

            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionStore = new DummyStore([
            new Projection($projector1->projectionId(), ProjectionStatus::Active),
            new Projection($projector2->projectionId(), ProjectionStatus::Active, 1),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->statefulProjectors()->willReturn([$projector1, $projector2])->shouldBeCalledOnce();

        $projectorRepository->findByProjectionId($projector1->projectionId())->willReturn($projector1)->shouldBeCalledTimes(
            2
        );

        $projectorRepository->findByProjectionId($projector2->projectionId())->willReturn($projector2)->shouldBeCalledTimes(
            1
        );

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveHandleMethod($projector1, $message)->willReturn($projector1->handle(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->run();

        self::assertEquals([
            new Projection($projector1->projectionId(), ProjectionStatus::Active, 1),
        ], $projectionStore->savedStates);

        self::assertSame($message, $projector1->message);
        self::assertNull($projector2->message);
    }

    public function testRunningWithError(): void
    {
        $projector = new class implements StatefulProjector {
            public function projectionId(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }

            public function handle(Message $message): void
            {
                throw new RuntimeException('ERROR');
            }
        };

        $projectionStore = new DummyStore([new Projection($projector->projectionId(), ProjectionStatus::Active)]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->statefulProjectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectionId($projector->projectionId())->willReturn($projector)->shouldBeCalledTimes(
            2
        );

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveHandleMethod($projector, $message)->willReturn($projector->handle(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->run();

        self::assertEquals([
            new Projection($projector->projectionId(), ProjectionStatus::Error, 0),
        ], $projectionStore->savedStates);
    }

    public function testRunningMarkOutdated(): void
    {
        $projectorId = new ProjectionId('test', 1);

        $projectionStore = new DummyStore([new Projection($projectorId, ProjectionStatus::Active)]);

        $generatorFactory = static function (): Generator {
            yield from [];
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->statefulProjectors()->willReturn([])->shouldBeCalledOnce();
        $projectorRepository->findByProjectionId($projectorId)->willReturn(null)->shouldBeCalledTimes(1);

        $projectorResolver = $this->prophesize(ProjectorResolver::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->run();

        self::assertEquals([
            new Projection($projectorId, ProjectionStatus::Outdated, 0),
        ], $projectionStore->savedStates);
    }

    public function testTeardownWithProjector(): void
    {
        $projector = new class implements StatefulProjector {
            public ?Message $message = null;
            public bool $dropped = false;

            public function projectionId(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }

            public function drop(): void
            {
                $this->dropped = true;
            }
        };

        $projectionStore = new DummyStore([new Projection($projector->projectionId(), ProjectionStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->statefulProjectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectionId($projector->projectionId())->willReturn($projector)->shouldBeCalledTimes(1);

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveDropMethod($projector)->willReturn($projector->drop(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->teardown();

        self::assertEquals([], $projectionStore->savedStates);
        self::assertEquals([$projector->projectionId()], $projectionStore->removedIds);
        self::assertTrue($projector->dropped);
    }

    public function testTeardownWithProjectorAndError(): void
    {
        $projector = new class implements StatefulProjector {
            public ?Message $message = null;
            public bool $dropped = false;

            public function projectionId(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }

            public function drop(): void
            {
                throw new RuntimeException('ERROR');
            }
        };

        $projectionStore = new DummyStore([new Projection($projector->projectionId(), ProjectionStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->statefulProjectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectionId($projector->projectionId())->willReturn($projector)->shouldBeCalledTimes(1);

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveDropMethod($projector)->willReturn($projector->drop(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->teardown();

        self::assertEquals([], $projectionStore->savedStates);
        self::assertEquals([], $projectionStore->removedIds);
    }

    public function testTeardownWithoutProjector(): void
    {
        $projectorId = new ProjectionId('test', 1);

        $projectionStore = new DummyStore([new Projection($projectorId, ProjectionStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->statefulProjectors()->willReturn([])->shouldBeCalledOnce();
        $projectorRepository->findByProjectionId($projectorId)->willReturn(null)->shouldBeCalledTimes(1);

        $projectorResolver = $this->prophesize(ProjectorResolver::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->teardown();

        self::assertEquals([], $projectionStore->savedStates);
        self::assertEquals([], $projectionStore->removedIds);
    }

    public function testRemoveWithProjector(): void
    {
        $projector = new class implements StatefulProjector {
            public bool $dropped = false;

            public function projectionId(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }

            public function drop(): void
            {
                $this->dropped = true;
            }
        };

        $projectionStore = new DummyStore([new Projection($projector->projectionId(), ProjectionStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->statefulProjectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectionId($projector->projectionId())->willReturn($projector)->shouldBeCalledTimes(1);

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveDropMethod($projector)->willReturn($projector->drop(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->remove();

        self::assertEquals([], $projectionStore->savedStates);
        self::assertEquals([$projector->projectionId()], $projectionStore->removedIds);
        self::assertTrue($projector->dropped);
    }

    public function testRemoveWithoutDropMethod(): void
    {
        $projector = new class implements StatefulProjector {
            public function projectionId(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }
        };

        $projectionStore = new DummyStore([new Projection($projector->projectionId(), ProjectionStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->statefulProjectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectionId($projector->projectionId())->willReturn($projector)->shouldBeCalledTimes(1);

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveDropMethod($projector)->willReturn(null);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->remove();

        self::assertEquals([], $projectionStore->savedStates);
        self::assertEquals([$projector->projectionId()], $projectionStore->removedIds);
    }

    public function testRemoveWithProjectorAndError(): void
    {
        $projector = new class implements StatefulProjector {
            public bool $dropped = false;

            public function projectionId(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }

            public function drop(): void
            {
                throw new RuntimeException('ERROR');
            }
        };

        $projectionStore = new DummyStore([new Projection($projector->projectionId(), ProjectionStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->statefulProjectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectionId($projector->projectionId())->willReturn($projector)->shouldBeCalledTimes(1);

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveDropMethod($projector)->willReturn($projector->drop(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->remove();

        self::assertEquals([], $projectionStore->savedStates);
        self::assertEquals([$projector->projectionId()], $projectionStore->removedIds);
    }

    public function testRemoveWithoutProjector(): void
    {
        $projectorId = new ProjectionId('test', 1);

        $projectionStore = new DummyStore([new Projection($projectorId, ProjectionStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->statefulProjectors()->willReturn([])->shouldBeCalledOnce();
        $projectorRepository->findByProjectionId($projectorId)->willReturn(null)->shouldBeCalledTimes(1);

        $projectorResolver = $this->prophesize(ProjectorResolver::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->remove();

        self::assertEquals([], $projectionStore->savedStates);
        self::assertEquals([$projectorId], $projectionStore->removedIds);
    }

    public function testReactivate(): void
    {
        $projector = new class implements StatefulProjector {
            public function projectionId(): ProjectionId
            {
                return new ProjectionId('test', 1);
            }
        };

        $projectionStore = new DummyStore([new Projection($projector->projectionId(), ProjectionStatus::Error)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->statefulProjectors()->willReturn([$projector])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->reactivate();

        self::assertEquals([
            new Projection($projector->projectionId(), ProjectionStatus::Active, 0),
        ], $projectionStore->savedStates);
    }
}
