<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\QueryBus;

use Patchlevel\EventSourcing\QueryBus\HandlerDescriptor;
use Patchlevel\EventSourcing\QueryBus\HandlerProvider;
use Patchlevel\EventSourcing\QueryBus\InvalidQueryHandler;
use Patchlevel\EventSourcing\QueryBus\SyncQueryBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(SyncQueryBus::class)]
final class SyncQueryBusTest extends TestCase
{
    public function testHandlerNotFound(): void
    {
        $query = new class {
        };

        $handlerProvider = $this->createMock(HandlerProvider::class);
        $handlerProvider->method('handlerForQuery')->with($query::class)->willReturn([]);

        $queryBus = new SyncQueryBus($handlerProvider);

        $this->expectException(InvalidQueryHandler::class);
        $this->expectExceptionMessage('No handler found for query ' . $query::class);

        $queryBus->dispatch($query);
    }

    public function testMultipleHandlersFound(): void
    {
        $query = new class {
        };

        $handlerProvider = $this->createMock(HandlerProvider::class);
        $handlerProvider->method('handlerForQuery')->with($query::class)->willReturn([
            new HandlerDescriptor(static fn () => null),
            new HandlerDescriptor(static fn () => null),
        ]);

        $queryBus = new SyncQueryBus($handlerProvider);

        $this->expectException(InvalidQueryHandler::class);
        $this->expectExceptionMessage('Multiple handlers found for query ' . $query::class);

        $queryBus->dispatch($query);
    }

    public function testHandleSuccess(): void
    {
        $query = new class {
        };

        $handler = new class {
            public object|null $query = null;

            public function __invoke(object $query): void
            {
                $this->query = $query;
            }
        };

        $handlerProvider = $this->createMock(HandlerProvider::class);
        $handlerProvider->method('handlerForQuery')->with($query::class)->willReturn([
            new HandlerDescriptor($handler),
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('debug')->with('QueryBus: dispatch query', ['query' => $query::class]);

        $queryBus = new SyncQueryBus($handlerProvider, $logger);
        $queryBus->dispatch($query);

        self::assertSame($query, $handler->query);
    }

    public function testIterableHandlerProviders(): void
    {
        $query = new class {
        };

        $handler = new class {
            public object|null $query = null;

            public function __invoke(object $query): void
            {
                $this->query = $query;
            }
        };

        $handlerProvider = $this->createMock(HandlerProvider::class);
        $handlerProvider
            ->expects($this->once())
            ->method('handlerForQuery')
            ->with($query::class)
            ->willReturn([
                new HandlerDescriptor($handler),
            ]);

        $queryBus = new SyncQueryBus([$handlerProvider]);
        $queryBus->dispatch($query);

        self::assertSame($query, $handler->query);
    }

    public function testHandlerGenerator(): void
    {
        $query = new class {
        };

        $handler = new class {
            public object|null $query = null;

            public function __invoke(object $query): void
            {
                $this->query = $query;
            }
        };

        $handlerProvider = $this->createMock(HandlerProvider::class);
        $handlerProvider
            ->expects($this->once())
            ->method('handlerForQuery')
            ->with($query::class)
            ->willReturnCallback(static function () use ($handler) {
                yield new HandlerDescriptor($handler);
            });

        $queryBus = new SyncQueryBus($handlerProvider);
        $queryBus->dispatch($query);

        self::assertSame($query, $handler->query);
    }
}
