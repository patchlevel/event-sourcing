<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projectionist;

use Patchlevel\EventSourcing\Attribute\Projector as ProjectionAttribute;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionError;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionStatus;
use Patchlevel\EventSourcing\Projection\Projection\Store\ErrorContext;
use Patchlevel\EventSourcing\Projection\Projectionist\DefaultProjectionist;
use Patchlevel\EventSourcing\Projection\Projectionist\ProjectionistCriteria;
use Patchlevel\EventSourcing\Store\ArrayStream;
use Patchlevel\EventSourcing\Store\Criteria;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Tests\Unit\Projection\DummyStore;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use RuntimeException;

/** @covers \Patchlevel\EventSourcing\Projection\Projectionist\DefaultProjectionist */
final class DefaultProjectionistTest extends TestCase
{
    use ProphecyTrait;

    public function testNothingToBoot(): void
    {
        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->shouldNotBeCalled();

        $store = new DummyStore();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $store,
            [],
        );

        $projectionist->boot();

        self::assertEquals([], $store->addedProjections);
        self::assertEquals([], $store->updatedProjections);
    }

    public function testBootDiscoverNewProjectors(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
        };

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([]))->shouldBeCalledOnce();

        $projectionStore = new DummyStore();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->boot();

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::New),
        ], $projectionStore->addedProjections);

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Booting),
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Active),
        ], $projectionStore->updatedProjections);
    }

    public function testBootWithoutCreateMethod(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
        };

        $projectionStore = new DummyStore([
            new Projection($projectionId),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->boot();

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Booting),
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Booting, 1),
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Active, 1),
        ], $projectionStore->updatedProjections);
    }

    public function testBootWithMethods(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
            public Message|null $message = null;
            public bool $created = false;

            #[Setup]
            public function create(): void
            {
                $this->created = true;
            }

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionStore = new DummyStore();

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->boot();

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::New),
        ], $projectionStore->addedProjections);

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Booting),
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Booting, 1),
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Active, 1),
        ], $projectionStore->updatedProjections);

        self::assertTrue($projector->created);
        self::assertSame($message, $projector->message);
    }

    public function testBootWithLimit(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
            public Message|null $message = null;
            public bool $created = false;

            #[Setup]
            public function create(): void
            {
                $this->created = true;
            }

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionStore = new DummyStore();

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->boot(new ProjectionistCriteria(), 1);

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::New),
        ], $projectionStore->addedProjections);

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Booting),
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Booting, 1),
        ], $projectionStore->updatedProjections);

        self::assertTrue($projector->created);
        self::assertSame($message, $projector->message);
    }

    public function testBootingWithSkip(): void
    {
        $projectionId1 = 'test1';
        $projector1 = new #[ProjectionAttribute('test1')]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionId2 = 'test2';
        $projector2 = new #[ProjectionAttribute('test2')]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionStore = new DummyStore([
            new Projection($projectionId1, Projection::DEFAULT_GROUP, ProjectionStatus::Booting),
            new Projection($projectionId2, Projection::DEFAULT_GROUP, ProjectionStatus::Booting, 1),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector1, $projector2],
        );

        $projectionist->boot();

        self::assertEquals([
            new Projection($projectionId1, Projection::DEFAULT_GROUP, ProjectionStatus::Booting, 1),
            new Projection($projectionId1, Projection::DEFAULT_GROUP, ProjectionStatus::Active, 1),
            new Projection($projectionId2, Projection::DEFAULT_GROUP, ProjectionStatus::Active, 1),
        ], $projectionStore->updatedProjections);

        self::assertSame($message, $projector1->message);
        self::assertNull($projector2->message);
    }

    public function testBootWithCreateError(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
            public function __construct(
                public readonly RuntimeException $exception = new RuntimeException('ERROR'),
            ) {
            }

            #[Setup]
            public function create(): void
            {
                throw $this->exception;
            }
        };

        $projectionStore = new DummyStore([
            new Projection($projectionId),
        ]);

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->shouldNotBeCalled();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->boot();

        self::assertEquals(
            [
                new Projection(
                    $projectionId,
                    Projection::DEFAULT_GROUP,
                    ProjectionStatus::Booting,
                ),
                new Projection(
                    $projectionId,
                    Projection::DEFAULT_GROUP,
                    ProjectionStatus::Error,
                    0,
                    new ProjectionError('ERROR', ErrorContext::fromThrowable($projector->exception)),
                    -1,
                ),
            ],
            $projectionStore->updatedProjections,
        );
    }

    public function testBootingWithGabInIndex(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
            /** @var list<Message> */
            public array $messages = [];

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->messages[] = $message;
            }
        };

        $projectionStore = new DummyStore([new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Booting)]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));
        $message2 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([1 => $message1, 3 => $message2]))->shouldBeCalledOnce();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->boot();

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Booting, 1),
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Booting, 3),
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Active, 3),
        ], $projectionStore->updatedProjections);

        self::assertSame([$message1, $message2], $projector->messages);
    }

    public function testBootingWithFromNow(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test', fromNow: true)]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionStore = new DummyStore([new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Booting)]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load(null, 1, null, true)->willReturn(new ArrayStream([$message1]))->shouldBeCalledOnce();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->boot();

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Active, 1),
        ], $projectionStore->updatedProjections);

        self::assertNull($projector->message);
    }

    public function testRunDiscoverNewProjectors(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
        };

        $streamableStore = $this->prophesize(Store::class);
        $projectionStore = new DummyStore();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->run();

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::New),
        ], $projectionStore->addedProjections);
    }

    public function testRunning(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionStore = new DummyStore([new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Active)]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->run();

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Active, 1),
        ], $projectionStore->updatedProjections);

        self::assertSame($message, $projector->message);
    }

    public function testRunningWithLimit(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionStore = new DummyStore([new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Active)]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));
        $message2 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore
            ->load($this->criteria())
            ->willReturn(new ArrayStream([$message1, $message2]))
            ->shouldBeCalledOnce();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->run(new ProjectionistCriteria(), 1);

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Active, 1),
        ], $projectionStore->updatedProjections);

        self::assertSame($message1, $projector->message);
    }

    public function testRunningWithSkip(): void
    {
        $projectionId1 = 'test1';
        $projector1 = new #[ProjectionAttribute('test1')]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionId2 = 'test2';
        $projector2 = new #[ProjectionAttribute('test2')]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionStore = new DummyStore([
            new Projection($projectionId1, Projection::DEFAULT_GROUP, ProjectionStatus::Active),
            new Projection($projectionId2, Projection::DEFAULT_GROUP, ProjectionStatus::Active, 1),
        ]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector1, $projector2],
        );

        $projectionist->run();

        self::assertEquals([
            new Projection($projectionId1, Projection::DEFAULT_GROUP, ProjectionStatus::Active, 1),
        ], $projectionStore->updatedProjections);

        self::assertSame($message, $projector1->message);
        self::assertNull($projector2->message);
    }

    public function testRunningWithError(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
            public function __construct(
                public readonly RuntimeException $exception = new RuntimeException('ERROR'),
            ) {
            }

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                throw $this->exception;
            }
        };

        $projectionStore = new DummyStore([new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Active)]);

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->run();

        self::assertEquals(
            [
                new Projection(
                    $projectionId,
                    Projection::DEFAULT_GROUP,
                    ProjectionStatus::Error,
                    0,
                    new ProjectionError('ERROR', ErrorContext::fromThrowable($projector->exception)),
                    1,
                ),
            ],
            $projectionStore->updatedProjections,
        );
    }

    public function testRunningMarkOutdated(): void
    {
        $projectionId = 'test';

        $projectionStore = new DummyStore([new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Active)]);

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->shouldNotBeCalled();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [],
        );

        $projectionist->run();

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Outdated, 0),
        ], $projectionStore->updatedProjections);
    }

    public function testRunningWithoutActiveProjectors(): void
    {
        $projectionId = 'test';

        $projectionStore = new DummyStore([new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Booting)]);

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->shouldNotBeCalled();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [],
        );

        $projectionist->run();

        self::assertEquals([], $projectionStore->updatedProjections);
    }

    public function testRunningWithGabInIndex(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
            /** @var list<Message> */
            public array $messages = [];

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->messages[] = $message;
            }
        };

        $projectionStore = new DummyStore([new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Active)]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));
        $message2 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([1 => $message1, 3 => $message2]))->shouldBeCalledOnce();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->run();

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Active, 1),
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Active, 3),
        ], $projectionStore->updatedProjections);

        self::assertSame([$message1, $message2], $projector->messages);
    }

    public function testTeardownDiscoverNewProjectors(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
        };

        $streamableStore = $this->prophesize(Store::class);
        $projectionStore = new DummyStore();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->teardown();

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::New),
        ], $projectionStore->addedProjections);
    }

    public function testTeardownWithProjector(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
            public Message|null $message = null;
            public bool $dropped = false;

            #[Teardown]
            public function drop(): void
            {
                $this->dropped = true;
            }
        };

        $projection = new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Outdated);

        $projectionStore = new DummyStore([$projection]);

        $streamableStore = $this->prophesize(Store::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->teardown();

        self::assertEquals([], $projectionStore->updatedProjections);
        self::assertEquals([$projection], $projectionStore->removedProjections);
        self::assertTrue($projector->dropped);
    }

    public function testTeardownWithProjectorAndError(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
            public Message|null $message = null;
            public bool $dropped = false;

            #[Teardown]
            public function drop(): void
            {
                throw new RuntimeException('ERROR');
            }
        };

        $projectionStore = new DummyStore([new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Outdated)]);

        $streamableStore = $this->prophesize(Store::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->teardown();

        self::assertEquals([], $projectionStore->updatedProjections);
        self::assertEquals([], $projectionStore->removedProjections);
    }

    public function testTeardownWithoutProjector(): void
    {
        $projectorId = 'test';

        $projectionStore = new DummyStore([new Projection($projectorId, Projection::DEFAULT_GROUP, ProjectionStatus::Outdated)]);

        $streamableStore = $this->prophesize(Store::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [],
        );

        $projectionist->teardown();

        self::assertEquals([], $projectionStore->updatedProjections);
        self::assertEquals([], $projectionStore->removedProjections);
    }

    public function testRemoveDiscoverNewProjectors(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
        };

        $streamableStore = $this->prophesize(Store::class);
        $projectionStore = new DummyStore();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->remove();

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::New),
        ], $projectionStore->addedProjections);
    }

    public function testRemoveWithProjector(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
            public bool $dropped = false;

            #[Teardown]
            public function drop(): void
            {
                $this->dropped = true;
            }
        };

        $projection = new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Outdated);
        $projectionStore = new DummyStore([$projection]);

        $streamableStore = $this->prophesize(Store::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->remove();

        self::assertEquals([], $projectionStore->updatedProjections);
        self::assertEquals([$projection], $projectionStore->removedProjections);
        self::assertTrue($projector->dropped);
    }

    public function testRemoveWithoutDropMethod(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
        };

        $projection = new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Outdated);
        $projectionStore = new DummyStore([$projection]);

        $streamableStore = $this->prophesize(Store::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->remove();

        self::assertEquals([], $projectionStore->updatedProjections);
        self::assertEquals([$projection], $projectionStore->removedProjections);
    }

    public function testRemoveWithProjectorAndError(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
            public bool $dropped = false;

            #[Teardown]
            public function drop(): void
            {
                throw new RuntimeException('ERROR');
            }
        };

        $projection = new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Outdated);
        $projectionStore = new DummyStore([$projection]);

        $streamableStore = $this->prophesize(Store::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->remove();

        self::assertEquals([], $projectionStore->updatedProjections);
        self::assertEquals([$projection], $projectionStore->removedProjections);
    }

    public function testRemoveWithoutProjector(): void
    {
        $projectorId = 'test';

        $projection = new Projection($projectorId, Projection::DEFAULT_GROUP, ProjectionStatus::Outdated);
        $projectionStore = new DummyStore([$projection]);

        $streamableStore = $this->prophesize(Store::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [],
        );

        $projectionist->remove();

        self::assertEquals([], $projectionStore->updatedProjections);
        self::assertEquals([$projection], $projectionStore->removedProjections);
    }

    public function testReactiveDiscoverNewProjectors(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
        };

        $streamableStore = $this->prophesize(Store::class);
        $projectionStore = new DummyStore();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->reactivate();

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::New),
        ], $projectionStore->addedProjections);
    }

    public function testReactivate(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
        };

        $projectionStore = new DummyStore([new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Error)]);

        $streamableStore = $this->prophesize(Store::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->reactivate();

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::Active, 0),
        ], $projectionStore->updatedProjections);
    }

    public function testGetProjectionAndDiscoverNewProjectors(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
        };

        $streamableStore = $this->prophesize(Store::class);
        $projectionStore = new DummyStore();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projections = $projectionist->projections();

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::New),
        ], $projectionStore->addedProjections);

        self::assertEquals([
            new Projection($projectionId, Projection::DEFAULT_GROUP, ProjectionStatus::New),
        ], $projections);
    }

    private function criteria(int $fromIndex = 0): Criteria
    {
        return new Criteria(fromIndex: $fromIndex);
    }
}
