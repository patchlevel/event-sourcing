<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\Projectionist;

use Closure;
use Generator;
use Patchlevel\EventSourcing\Attribute\Projector as ProjectionAttribute;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionError;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionStatus;
use Patchlevel\EventSourcing\Projection\Projection\RunMode;
use Patchlevel\EventSourcing\Projection\Projection\Store\LockableProjectionStore;
use Patchlevel\EventSourcing\Projection\Projection\Store\ProjectionStore;
use Patchlevel\EventSourcing\Projection\Projection\ThrowableToErrorContextTransformer;
use Patchlevel\EventSourcing\Projection\Projectionist\DefaultProjectionist;
use Patchlevel\EventSourcing\Projection\Projectionist\ProjectionistCriteria;
use Patchlevel\EventSourcing\Projection\RetryStrategy\RetryStrategy;
use Patchlevel\EventSourcing\Store\ArrayStream;
use Patchlevel\EventSourcing\Store\Criteria;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Tests\Unit\Projection\DummyStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
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
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::New,
            ),
        ], $projectionStore->addedProjections);

        self::assertEquals([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Booting,
            ),
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
            ),
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
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Booting,
            ),
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Booting,
                1,
            ),
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
                1,
            ),
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
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::New,
            ),
        ], $projectionStore->addedProjections);

        self::assertEquals([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Booting,
            ),
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Booting,
                1,
            ),
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
                1,
            ),
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
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::New,
            ),
        ], $projectionStore->addedProjections);

        self::assertEquals([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Booting,
            ),
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Booting,
                1,
            ),
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
            new Projection(
                $projectionId1,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Booting,
            ),
            new Projection(
                $projectionId2,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Booting,
                1,
            ),
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
            new Projection(
                $projectionId1,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Booting,
                1,
            ),
            new Projection(
                $projectionId1,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
                1,
            ),
            new Projection(
                $projectionId2,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
                1,
            ),
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
                    RunMode::FromBeginning,
                    ProjectionStatus::Error,
                    0,
                    new ProjectionError(
                        'ERROR',
                        ProjectionStatus::New,
                        ThrowableToErrorContextTransformer::transform($projector->exception),
                    ),
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

        $projectionStore = new DummyStore([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Booting,
            ),
        ]);

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
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Booting,
                1,
            ),
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Booting,
                3,
            ),
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
                3,
            ),
        ], $projectionStore->updatedProjections);

        self::assertSame([$message1, $message2], $projector->messages);
    }

    public function testBootingWithFromNow(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test', runMode: RunMode::FromNow)]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionStore = new DummyStore([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromNow,
                ProjectionStatus::Booting,
            ),
        ]);

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
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromNow,
                ProjectionStatus::Active,
                1,
            ),
        ], $projectionStore->updatedProjections);

        self::assertNull($projector->message);
    }

    public function testBootingWithOnlyOnce(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test', runMode: RunMode::Once)]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileVisited::class)]
            public function handle(Message $message): void
            {
                $this->message = $message;
            }
        };

        $projectionStore = new DummyStore([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::Once,
                ProjectionStatus::Booting,
            ),
        ]);

        $message1 = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message1]))->shouldBeCalledOnce();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->boot();

        self::assertEquals([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::Once,
                ProjectionStatus::Booting,
                1,
            ),
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::Once,
                ProjectionStatus::Finished,
                1,
            ),
        ], $projectionStore->updatedProjections);

        self::assertEquals($message1, $projector->message);
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
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::New,
            ),
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

        $projectionStore = new DummyStore([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
            ),
        ]);

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
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
                1,
            ),
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

        $projectionStore = new DummyStore([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
            ),
        ]);

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
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
                1,
            ),
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
            new Projection(
                $projectionId1,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
            ),
            new Projection(
                $projectionId2,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
                1,
            ),
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
            new Projection(
                $projectionId1,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
                1,
            ),
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

        $projectionStore = new DummyStore([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
            ),
        ]);

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
                    RunMode::FromBeginning,
                    ProjectionStatus::Error,
                    0,
                    new ProjectionError(
                        'ERROR',
                        ProjectionStatus::Active,
                        ThrowableToErrorContextTransformer::transform($projector->exception),
                    ),
                ),
            ],
            $projectionStore->updatedProjections,
        );
    }

    public function testRunningMarkOutdated(): void
    {
        $projectionId = 'test';

        $projectionStore = new DummyStore([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->shouldNotBeCalled();

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [],
        );

        $projectionist->run();

        self::assertEquals([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Outdated,
                0,
            ),
        ], $projectionStore->updatedProjections);
    }

    public function testRunningWithoutActiveProjectors(): void
    {
        $projectionId = 'test';

        $projectionStore = new DummyStore([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Booting,
            ),
        ]);

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

        $projectionStore = new DummyStore([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
            ),
        ]);

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
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
                1,
            ),
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
                3,
            ),
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
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::New,
            ),
        ], $projectionStore->addedProjections);
    }

    public function testTeardownWithoutTeardownMethod(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
        };

        $projection = new Projection(
            $projectionId,
            Projection::DEFAULT_GROUP,
            RunMode::FromBeginning,
            ProjectionStatus::Outdated,
        );

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

        $projection = new Projection(
            $projectionId,
            Projection::DEFAULT_GROUP,
            RunMode::FromBeginning,
            ProjectionStatus::Outdated,
        );

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

        $projectionStore = new DummyStore([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Outdated,
            ),
        ]);

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

        $projectionStore = new DummyStore([
            new Projection(
                $projectorId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Outdated,
            ),
        ]);

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
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::New,
            ),
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

        $projection = new Projection(
            $projectionId,
            Projection::DEFAULT_GROUP,
            RunMode::FromBeginning,
            ProjectionStatus::Outdated,
        );
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

        $projection = new Projection(
            $projectionId,
            Projection::DEFAULT_GROUP,
            RunMode::FromBeginning,
            ProjectionStatus::Outdated,
        );
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

        $projection = new Projection(
            $projectionId,
            Projection::DEFAULT_GROUP,
            RunMode::FromBeginning,
            ProjectionStatus::Outdated,
        );
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

        $projection = new Projection(
            $projectorId,
            Projection::DEFAULT_GROUP,
            RunMode::FromBeginning,
            ProjectionStatus::Outdated,
        );
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
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::New,
            ),
        ], $projectionStore->addedProjections);
    }

    public function testReactivateError(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
        };

        $projectionStore = new DummyStore([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Error,
                0,
                new ProjectionError('ERROR', ProjectionStatus::New),
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->reactivate();

        self::assertEquals([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::New,
                0,
            ),
        ], $projectionStore->updatedProjections);
    }

    public function testReactivateOutdated(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
        };

        $projectionStore = new DummyStore([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Outdated,
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->reactivate();

        self::assertEquals([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
            ),
        ], $projectionStore->updatedProjections);
    }

    public function testReactivatePaused(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
        };

        $projectionStore = new DummyStore([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Paused,
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->reactivate();

        self::assertEquals([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
            ),
        ], $projectionStore->updatedProjections);
    }

    public function testReactivateFinished(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
        };

        $projectionStore = new DummyStore([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Finished,
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->reactivate();

        self::assertEquals([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
            ),
        ], $projectionStore->updatedProjections);
    }

    public function testPauseDiscoverNewProjectors(): void
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

        $projectionist->pause();

        self::assertEquals([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::New,
            ),
        ], $projectionStore->addedProjections);
    }

    public function testPauseBooting(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
        };

        $projectionStore = new DummyStore([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Booting,
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->pause();

        self::assertEquals([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Paused,
            ),
        ], $projectionStore->updatedProjections);
    }

    public function testPauseActive(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
        };

        $projectionStore = new DummyStore([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Active,
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->pause();

        self::assertEquals([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Paused,
            ),
        ], $projectionStore->updatedProjections);
    }

    public function testPauseError(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
        };

        $projectionStore = new DummyStore([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Error,
                0,
                new ProjectionError('ERROR', ProjectionStatus::New),
            ),
        ]);

        $streamableStore = $this->prophesize(Store::class);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
        );

        $projectionist->pause();

        self::assertEquals([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::Paused,
                0,
                new ProjectionError('ERROR', ProjectionStatus::New),
            ),
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
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::New,
            ),
        ], $projectionStore->addedProjections);

        self::assertEquals([
            new Projection(
                $projectionId,
                Projection::DEFAULT_GROUP,
                RunMode::FromBeginning,
                ProjectionStatus::New,
            ),
        ], $projections);
    }

    public function testRetry(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
            #[Subscribe(ProfileVisited::class)]
            public function subscribe(): void
            {
                throw new RuntimeException('ERROR2');
            }
        };

        $message = new Message(new ProfileVisited(ProfileId::fromString('test')));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([$message]))->shouldBeCalledOnce();

        $projection = new Projection(
            $projectionId,
            Projection::DEFAULT_GROUP,
            RunMode::FromBeginning,
            ProjectionStatus::Error,
            0,
            new ProjectionError('ERROR', ProjectionStatus::Active),
        );

        $projectionStore = new DummyStore([$projection]);

        $retryStrategy = $this->prophesize(RetryStrategy::class);
        $retryStrategy->shouldRetry($projection)->willReturn(true);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
            $retryStrategy->reveal(),
        );

        $projectionist->run();

        self::assertCount(2, $projectionStore->updatedProjections);

        [$update1, $update2] = $projectionStore->updatedProjections;

        self::assertEquals($update1, new Projection(
            $projectionId,
            Projection::DEFAULT_GROUP,
            RunMode::FromBeginning,
            ProjectionStatus::Active,
            0,
            null,
            1,
        ));

        self::assertEquals(ProjectionStatus::Error, $update2->status());
        self::assertEquals(ProjectionStatus::Active, $update2->projectionError()?->previousStatus);
        self::assertEquals('ERROR2', $update2->projectionError()?->errorMessage);
        self::assertEquals(1, $update2->retryAttempt());
    }

    public function testShouldNotRetry(): void
    {
        $projectionId = 'test';
        $projector = new #[ProjectionAttribute('test')]
        class {
        };

        $streamableStore = $this->prophesize(Store::class);

        $projection = new Projection(
            $projectionId,
            Projection::DEFAULT_GROUP,
            RunMode::FromBeginning,
            ProjectionStatus::Error,
            0,
            new ProjectionError('ERROR', ProjectionStatus::Active),
        );

        $projectionStore = new DummyStore([$projection]);

        $retryStrategy = $this->prophesize(RetryStrategy::class);
        $retryStrategy->shouldRetry($projection)->willReturn(false);

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore,
            [$projector],
            $retryStrategy->reveal(),
        );

        $projectionist->run();

        self::assertEquals([], $projectionStore->updatedProjections);
    }

    #[DataProvider('methodProvider')]
    public function testCriteria(string $method): void
    {
        $projector = new #[ProjectionAttribute('id1')]
        class {
        };

        $projectionStore = $this->prophesize(ProjectionStore::class);
        $projectionStore->find(
            Argument::that(
                static fn (ProjectionCriteria $criteria) => $criteria->ids === ['id1'] && $criteria->groups === ['group1']
            ),
        )->willReturn([])->shouldBeCalled();

        $projectionStore->find(
            new ProjectionCriteria(),
        )->willReturn([
            new Projection('id1'),
        ])->shouldBeCalled();

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([]));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore->reveal(),
            [$projector],
        );

        $projectionistCriteria = new ProjectionistCriteria(
            ids: ['id1'],
            groups: ['group1'],
        );

        $projectionist->{$method}($projectionistCriteria);
    }

    #[DataProvider('methodProvider')]
    public function testWithLockableStore(string $method): void
    {
        $projector = new #[ProjectionAttribute('id1')]
        class {
        };

        $projectionStore = $this->prophesize(LockableProjectionStore::class);
        $projectionStore->inLock(Argument::type(Closure::class))->will(
        /** @param array{Closure} $args */
            static fn (array $args): mixed => $args[0]()
        )->shouldBeCalled();
        $projectionStore->find(Argument::any())->willReturn([])->shouldBeCalled();

        $projectionStore->find(
            new ProjectionCriteria(),
        )->willReturn([
            new Projection('id1'),
        ])->shouldBeCalled();

        $projectionStore->remove(Argument::type(Projection::class));
        $projectionStore->add(Argument::type(Projection::class));

        $streamableStore = $this->prophesize(Store::class);
        $streamableStore->load($this->criteria())->willReturn(new ArrayStream([]));

        $projectionist = new DefaultProjectionist(
            $streamableStore->reveal(),
            $projectionStore->reveal(),
            [$projector],
        );

        $projectionist->{$method}();
    }

    public static function methodProvider(): Generator
    {
        yield 'boot' => ['boot'];
        yield 'run' => ['run'];
        yield 'teardown' => ['teardown'];
        yield 'remove' => ['remove'];
        yield 'reactivate' => ['reactivate'];
        yield 'projections' => ['projections'];
    }

    private function criteria(int $fromIndex = 0): Criteria
    {
        return new Criteria(fromIndex: $fromIndex);
    }
}
