<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\CommandBus;

use Patchlevel\EventSourcing\CommandBus\HandlerDescriptor;
use Patchlevel\EventSourcing\CommandBus\HandlerNotFound;
use Patchlevel\EventSourcing\CommandBus\HandlerProvider;
use Patchlevel\EventSourcing\CommandBus\MultipleHandlersFound;
use Patchlevel\EventSourcing\CommandBus\SyncCommandBus;
use Patchlevel\EventSourcing\Identifier\FakeRamseyUuidFactory;
use Patchlevel\EventSourcing\Message\Context\MessageContext;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\CreateProfile;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid as RamseyUuid;
use RuntimeException;

#[CoversClass(SyncCommandBus::class)]
final class SyncCommandBusTest extends TestCase
{
    public function testHandlerNotFound(): void
    {
        $command = new class {
        };

        $handlerProvider = $this->createMock(HandlerProvider::class);
        $handlerProvider->method('handlerForCommand')->with($command::class)->willReturn([]);

        $commandBus = new SyncCommandBus($handlerProvider);

        $this->expectException(HandlerNotFound::class);

        $commandBus->dispatch($command);
    }

    public function testMultipleHandlersFound(): void
    {
        $command = new class {
        };

        $handlerProvider = $this->createMock(HandlerProvider::class);
        $handlerProvider->method('handlerForCommand')->with($command::class)->willReturn([
            new HandlerDescriptor(static fn () => null),
            new HandlerDescriptor(static fn () => null),
        ]);

        $commandBus = new SyncCommandBus($handlerProvider);

        $this->expectException(MultipleHandlersFound::class);

        $commandBus->dispatch($command);
    }

    public function testHandleSuccess(): void
    {
        $command = new class {
        };

        $handler = new class {
            public object|null $command = null;

            public function __invoke(object $command): void
            {
                $this->command = $command;
            }
        };

        $handlerProvider = $this->createMock(HandlerProvider::class);
        $handlerProvider->method('handlerForCommand')->with($command::class)->willReturn([
            new HandlerDescriptor($handler),
        ]);

        $commandBus = new SyncCommandBus($handlerProvider);

        $commandBus->dispatch($command);

        self::assertSame($command, $handler->command);
    }

    public function testIterableHandlerProviders(): void
    {
        $command = new class {
        };

        $handler = new class {
            public object|null $command = null;

            public function __invoke(object $command): void
            {
                $this->command = $command;
            }
        };

        $handlerProvider = $this->createMock(HandlerProvider::class);
        $handlerProvider
            ->expects($this->once())
            ->method('handlerForCommand')
            ->with($command::class)
            ->willReturn([
                new HandlerDescriptor($handler),
            ]);

        $commandBus = new SyncCommandBus([$handlerProvider]);

        $commandBus->dispatch($command);

        self::assertSame($command, $handler->command);
    }

    public function testHandlerGenerator(): void
    {
        $command = new class {
        };

        $handler = new class {
            public object|null $command = null;

            public function __invoke(object $command): void
            {
                $this->command = $command;
            }
        };

        $handlerProvider = $this->createMock(HandlerProvider::class);
        $handlerProvider
            ->expects($this->once())
            ->method('handlerForCommand')
            ->with($command::class)
            ->willReturnCallback(static function () use ($handler) {
                yield new HandlerDescriptor($handler);
            });

        $commandBus = new SyncCommandBus($handlerProvider);

        $commandBus->dispatch($command);

        self::assertSame($command, $handler->command);
    }

    public function testCreateForAggregateHandlers(): void
    {
        $repositoryManager = $this->createMock(RepositoryManager::class);

        $commandBus = SyncCommandBus::createForAggregateHandlers(
            new AggregateRootRegistry([]),
            $repositoryManager,
        );

        $this->expectException(HandlerNotFound::class);
        $commandBus->dispatch(new CreateProfile(ProfileId::fromString('1'), 'foo'));
    }

    public function testSeedsCorrelationIdForDispatch(): void
    {
        $previousUuidFactory = RamseyUuid::getFactory();
        RamseyUuid::setFactory(new FakeRamseyUuidFactory());

        try {
            $command = new class {
            };

            $context = new MessageContext();

            $handler = new class ($context) {
                public string|null $causationId = null;
                public string|null $correlationId = null;

                public function __construct(private readonly MessageContext $context)
                {
                }

                public function __invoke(object $command): void
                {
                    $this->causationId = $this->context->causationId();
                    $this->correlationId = $this->context->correlationId();
                }
            };

            $handlerProvider = $this->createMock(HandlerProvider::class);
            $handlerProvider
                ->expects($this->once())
                ->method('handlerForCommand')
                ->with($command::class)
                ->willReturn([new HandlerDescriptor($handler)]);

            $commandBus = new SyncCommandBus($handlerProvider, null, $context);
            $commandBus->dispatch($command);

            self::assertNull($handler->causationId);
            self::assertSame('10000000-7000-0000-0000-000000000001', $handler->correlationId);
            self::assertNull($context->correlationId());
        } finally {
            RamseyUuid::setFactory($previousUuidFactory);
        }
    }

    public function testInheritsIdsFromOuterContext(): void
    {
        $command = new class {
        };

        $context = new MessageContext();
        $context->push('causation-1', 'correlation-1');

        $handler = new class ($context) {
            public string|null $causationId = null;
            public string|null $correlationId = null;

            public function __construct(private readonly MessageContext $context)
            {
            }

            public function __invoke(object $command): void
            {
                $this->causationId = $this->context->causationId();
                $this->correlationId = $this->context->correlationId();
            }
        };

        $handlerProvider = $this->createMock(HandlerProvider::class);
        $handlerProvider
            ->expects($this->once())
            ->method('handlerForCommand')
            ->with($command::class)
            ->willReturn([new HandlerDescriptor($handler)]);

        $commandBus = new SyncCommandBus($handlerProvider, null, $context);
        $commandBus->dispatch($command);

        self::assertSame('causation-1', $handler->causationId);
        self::assertSame('correlation-1', $handler->correlationId);
        self::assertSame('correlation-1', $context->correlationId());
    }

    public function testPopsContextOnException(): void
    {
        $command = new class {
        };

        $context = new MessageContext();
        $context->push('causation-1', 'correlation-1');

        $handlerProvider = $this->createMock(HandlerProvider::class);
        $handlerProvider
            ->expects($this->once())
            ->method('handlerForCommand')
            ->with($command::class)
            ->willReturn([
                new HandlerDescriptor(
                    static fn (object $command) => throw new RuntimeException('ERROR'),
                ),
            ]);

        $commandBus = new SyncCommandBus($handlerProvider, null, $context);

        try {
            $commandBus->dispatch($command);
        } catch (RuntimeException) {
            // expected
        }

        self::assertSame('correlation-1', $context->correlationId());
    }
}
