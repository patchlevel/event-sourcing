<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Generator;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\DefaultProjectionist;
use Patchlevel\EventSourcing\Projection\Projector;
use Patchlevel\EventSourcing\Projection\ProjectorCriteria;
use Patchlevel\EventSourcing\Projection\ProjectorId;
use Patchlevel\EventSourcing\Projection\ProjectorRepository;
use Patchlevel\EventSourcing\Projection\ProjectorResolver;
use Patchlevel\EventSourcing\Projection\ProjectorStatus;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorState;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStateCollection;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStore;
use Patchlevel\EventSourcing\Store\StreamableStore;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use RuntimeException;

/** @covers \Patchlevel\EventSourcing\Projection\DefaultProjectionist */
final class DefaultProjectionistTest extends TestCase
{
    use ProphecyTrait;

    public function testNothingToBoot(): void
    {
        $projectorStateCollection = new ProjectorStateCollection();
        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorStore = $this->prophesize(ProjectorStore::class);
        $projectorStore->getStateFromAllProjectors()->willReturn($projectorStateCollection)->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectorStore->reveal(),
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->boot();
    }

    public function testBootWithoutCreateMethod(): void
    {
        $projector = new class implements Projector {
            public function projectorId(): ProjectorId
            {
                return new ProjectorId('test', 1);
            }
        };

        $projectorStore = new DummyStore([
            new ProjectorState($projector->projectorId()),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectorId($projector->projectorId())->willReturn($projector)->shouldBeCalledTimes(
            2
        );

        $projectorResolver = $this->prophesize(ProjectorResolver::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectorStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->boot();

        self::assertEquals([
            new ProjectorState($projector->projectorId(), ProjectorStatus::Booting),
            new ProjectorState($projector->projectorId(), ProjectorStatus::Booting, 1),
            new ProjectorState($projector->projectorId(), ProjectorStatus::Active, 1),
        ], $projectorStore->savedStates);
    }

    public function testBootWithMethods(): void
    {
        $projector = new class implements Projector {
            public ?Message $message = null;
            public bool $created = false;

            public function projectorId(): ProjectorId
            {
                return new ProjectorId('test', 1);
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

        $projectorStore = new DummyStore();

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectorId($projector->projectorId())->willReturn($projector)->shouldBeCalledTimes(
            2
        );

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveCreateMethod($projector)->willReturn($projector->create(...));
        $projectorResolver->resolveHandleMethod($projector, $message)->willReturn($projector->handle(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectorStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->boot();

        self::assertEquals([
            new ProjectorState($projector->projectorId(), ProjectorStatus::Booting),
            new ProjectorState($projector->projectorId(), ProjectorStatus::Booting, 1),
            new ProjectorState($projector->projectorId(), ProjectorStatus::Active, 1),
        ], $projectorStore->savedStates);

        self::assertTrue($projector->created);
        self::assertSame($message, $projector->message);
    }

    public function testBootWithLimit(): void
    {
        $projector = new class implements Projector {
            public ?Message $message = null;
            public bool $created = false;

            public function projectorId(): ProjectorId
            {
                return new ProjectorId('test', 1);
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

        $projectorStore = new DummyStore();

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectorId($projector->projectorId())->willReturn($projector)->shouldBeCalledTimes(
            2
        );

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveCreateMethod($projector)->willReturn($projector->create(...));
        $projectorResolver->resolveHandleMethod($projector, $message)->willReturn($projector->handle(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectorStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->boot(new ProjectorCriteria(), 1);

        self::assertEquals([
            new ProjectorState($projector->projectorId(), ProjectorStatus::Booting),
            new ProjectorState($projector->projectorId(), ProjectorStatus::Booting, 1),
        ], $projectorStore->savedStates);

        self::assertTrue($projector->created);
        self::assertSame($message, $projector->message);
    }

    public function testBootWithCreateError(): void
    {
        $projector = new class implements Projector {
            public ?Message $message = null;
            public bool $created = false;

            public function projectorId(): ProjectorId
            {
                return new ProjectorId('test', 1);
            }

            public function create(): void
            {
                throw new RuntimeException('ERROR');
            }
        };

        $projectorStore = new DummyStore([
            new ProjectorState($projector->projectorId()),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectorId($projector->projectorId())->willReturn($projector)->shouldBeCalledTimes(
            1
        );

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveCreateMethod($projector)->willReturn($projector->create(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectorStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->boot();

        self::assertEquals([
            new ProjectorState($projector->projectorId(), ProjectorStatus::Booting),
            new ProjectorState($projector->projectorId(), ProjectorStatus::Error),
        ], $projectorStore->savedStates);
    }

    public function testRunning(): void
    {
        $projector = new class implements Projector {
            public ?Message $message = null;

            public function projectorId(): ProjectorId
            {
                return new ProjectorId('test', 1);
            }

            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectorStore = new DummyStore([new ProjectorState($projector->projectorId(), ProjectorStatus::Active)]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectorId($projector->projectorId())->willReturn($projector)->shouldBeCalledTimes(
            2
        );

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveHandleMethod($projector, $message)->willReturn($projector->handle(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectorStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->run();

        self::assertEquals([
            new ProjectorState($projector->projectorId(), ProjectorStatus::Active, 1),
        ], $projectorStore->savedStates);

        self::assertSame($message, $projector->message);
    }

    public function testRunningWithLimit(): void
    {
        $projector = new class implements Projector {
            public ?Message $message = null;

            public function projectorId(): ProjectorId
            {
                return new ProjectorId('test', 1);
            }

            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectorStore = new DummyStore([new ProjectorState($projector->projectorId(), ProjectorStatus::Active)]);

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
        $projectorRepository->findByProjectorId($projector->projectorId())->willReturn($projector)->shouldBeCalledTimes(
            2
        );

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveHandleMethod($projector, $message1)->willReturn($projector->handle(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectorStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->run(new ProjectorCriteria(), 1);

        self::assertEquals([
            new ProjectorState($projector->projectorId(), ProjectorStatus::Active, 1),
        ], $projectorStore->savedStates);

        self::assertSame($message1, $projector->message);
    }

    public function testRunningWithSkip(): void
    {
        $projector1 = new class implements Projector {
            public ?Message $message = null;

            public function projectorId(): ProjectorId
            {
                return new ProjectorId('test1', 1);
            }

            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projector2 = new class implements Projector {
            public ?Message $message = null;

            public function projectorId(): ProjectorId
            {
                return new ProjectorId('test2', 1);
            }

            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectorStore = new DummyStore([
            new ProjectorState($projector1->projectorId(), ProjectorStatus::Active),
            new ProjectorState($projector2->projectorId(), ProjectorStatus::Active, 1),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector1, $projector2])->shouldBeCalledOnce();

        $projectorRepository->findByProjectorId($projector1->projectorId())->willReturn($projector1)->shouldBeCalledTimes(
            2
        );

        $projectorRepository->findByProjectorId($projector2->projectorId())->willReturn($projector2)->shouldBeCalledTimes(
            1
        );

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveHandleMethod($projector1, $message)->willReturn($projector1->handle(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectorStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->run();

        self::assertEquals([
            new ProjectorState($projector1->projectorId(), ProjectorStatus::Active, 1),
        ], $projectorStore->savedStates);

        self::assertSame($message, $projector1->message);
        self::assertNull($projector2->message);
    }

    public function testRunningWithError(): void
    {
        $projector = new class implements Projector {
            public function projectorId(): ProjectorId
            {
                return new ProjectorId('test', 1);
            }

            public function handle(Message $message): void
            {
                throw new RuntimeException('ERROR');
            }
        };

        $projectorStore = new DummyStore([new ProjectorState($projector->projectorId(), ProjectorStatus::Active)]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $generatorFactory = static function () use ($message): Generator {
            yield $message;
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectorId($projector->projectorId())->willReturn($projector)->shouldBeCalledTimes(
            2
        );

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveHandleMethod($projector, $message)->willReturn($projector->handle(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectorStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->run();

        self::assertEquals([
            new ProjectorState($projector->projectorId(), ProjectorStatus::Error, 0),
        ], $projectorStore->savedStates);
    }

    public function testRunningMarkOutdated(): void
    {
        $projectorId = new ProjectorId('test', 1);

        $projectorStore = new DummyStore([new ProjectorState($projectorId, ProjectorStatus::Active)]);

        $generatorFactory = static function (): Generator {
            yield from [];
        };

        $streamableStore = $this->prophesize(StreamableStore::class);
        $streamableStore->stream(0)->willReturn($generatorFactory())->shouldBeCalledOnce();

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([])->shouldBeCalledOnce();
        $projectorRepository->findByProjectorId($projectorId)->willReturn(null)->shouldBeCalledTimes(1);

        $projectorResolver = $this->prophesize(ProjectorResolver::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectorStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->run();

        self::assertEquals([
            new ProjectorState($projectorId, ProjectorStatus::Outdated, 0),
        ], $projectorStore->savedStates);
    }

    public function testTeardownWithProjector(): void
    {
        $projector = new class implements Projector {
            public ?Message $message = null;
            public bool $dropped = false;

            public function projectorId(): ProjectorId
            {
                return new ProjectorId('test', 1);
            }

            public function drop(): void
            {
                $this->dropped = true;
            }
        };

        $projectorStore = new DummyStore([new ProjectorState($projector->projectorId(), ProjectorStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectorId($projector->projectorId())->willReturn($projector)->shouldBeCalledTimes(1);

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveDropMethod($projector)->willReturn($projector->drop(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectorStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->teardown();

        self::assertEquals([], $projectorStore->savedStates);
        self::assertEquals([$projector->projectorId()], $projectorStore->removedIds);
        self::assertTrue($projector->dropped);
    }

    public function testTeardownWithProjectorAndError(): void
    {
        $projector = new class implements Projector {
            public ?Message $message = null;
            public bool $dropped = false;

            public function projectorId(): ProjectorId
            {
                return new ProjectorId('test', 1);
            }

            public function drop(): void
            {
                throw new RuntimeException('ERROR');
            }
        };

        $projectorStore = new DummyStore([new ProjectorState($projector->projectorId(), ProjectorStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectorId($projector->projectorId())->willReturn($projector)->shouldBeCalledTimes(1);

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveDropMethod($projector)->willReturn($projector->drop(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectorStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->teardown();

        self::assertEquals([], $projectorStore->savedStates);
        self::assertEquals([], $projectorStore->removedIds);
    }

    public function testTeardownWithoutProjector(): void
    {
        $projectorId = new ProjectorId('test', 1);

        $projectorStore = new DummyStore([new ProjectorState($projectorId, ProjectorStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([])->shouldBeCalledOnce();
        $projectorRepository->findByProjectorId($projectorId)->willReturn(null)->shouldBeCalledTimes(1);

        $projectorResolver = $this->prophesize(ProjectorResolver::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectorStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->teardown();

        self::assertEquals([], $projectorStore->savedStates);
        self::assertEquals([], $projectorStore->removedIds);
    }

    public function testRemoveWithProjector(): void
    {
        $projector = new class implements Projector {
            public bool $dropped = false;

            public function projectorId(): ProjectorId
            {
                return new ProjectorId('test', 1);
            }

            public function drop(): void
            {
                $this->dropped = true;
            }
        };

        $projectorStore = new DummyStore([new ProjectorState($projector->projectorId(), ProjectorStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectorId($projector->projectorId())->willReturn($projector)->shouldBeCalledTimes(1);

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveDropMethod($projector)->willReturn($projector->drop(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectorStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->remove();

        self::assertEquals([], $projectorStore->savedStates);
        self::assertEquals([$projector->projectorId()], $projectorStore->removedIds);
        self::assertTrue($projector->dropped);
    }

    public function testRemoveWithoutDropMethod(): void
    {
        $projector = new class implements Projector {
            public function projectorId(): ProjectorId
            {
                return new ProjectorId('test', 1);
            }
        };

        $projectorStore = new DummyStore([new ProjectorState($projector->projectorId(), ProjectorStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectorId($projector->projectorId())->willReturn($projector)->shouldBeCalledTimes(1);

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveDropMethod($projector)->willReturn(null);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectorStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->remove();

        self::assertEquals([], $projectorStore->savedStates);
        self::assertEquals([$projector->projectorId()], $projectorStore->removedIds);
    }

    public function testRemoveWithProjectorAndError(): void
    {
        $projector = new class implements Projector {
            public bool $dropped = false;

            public function projectorId(): ProjectorId
            {
                return new ProjectorId('test', 1);
            }

            public function drop(): void
            {
                throw new RuntimeException('ERROR');
            }
        };

        $projectorStore = new DummyStore([new ProjectorState($projector->projectorId(), ProjectorStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();
        $projectorRepository->findByProjectorId($projector->projectorId())->willReturn($projector)->shouldBeCalledTimes(1);

        $projectorResolver = $this->prophesize(ProjectorResolver::class);
        $projectorResolver->resolveDropMethod($projector)->willReturn($projector->drop(...));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectorStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->remove();

        self::assertEquals([], $projectorStore->savedStates);
        self::assertEquals([$projector->projectorId()], $projectorStore->removedIds);
    }

    public function testRemoveWithoutProjector(): void
    {
        $projectorId = new ProjectorId('test', 1);

        $projectorStore = new DummyStore([new ProjectorState($projectorId, ProjectorStatus::Outdated)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([])->shouldBeCalledOnce();
        $projectorRepository->findByProjectorId($projectorId)->willReturn(null)->shouldBeCalledTimes(1);

        $projectorResolver = $this->prophesize(ProjectorResolver::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectorStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->remove();

        self::assertEquals([], $projectorStore->savedStates);
        self::assertEquals([$projectorId], $projectorStore->removedIds);
    }

    public function testReactivate(): void
    {
        $projector = new class implements Projector {
            public function projectorId(): ProjectorId
            {
                return new ProjectorId('test', 1);
            }
        };

        $projectorStore = new DummyStore([new ProjectorState($projector->projectorId(), ProjectorStatus::Error)]);

        $streamableStore = $this->prophesize(StreamableStore::class);

        $projectorRepository = $this->prophesize(ProjectorRepository::class);
        $projectorRepository->projectors()->willReturn([$projector])->shouldBeCalledOnce();

        $projectorResolver = $this->prophesize(ProjectorResolver::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectorStore,
            $projectorRepository->reveal(),
            $projectorResolver->reveal(),
            new NullLogger()
        );

        $projectionist->reactivate();

        self::assertEquals([
            new ProjectorState($projector->projectorId(), ProjectorStatus::Active, 0),
        ], $projectorStore->savedStates);
    }
}
