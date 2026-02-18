<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Cleanup\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\Persistence\ConnectionRegistry;
use Patchlevel\EventSourcing\Subscription\Cleanup\CleanupTaskNotSupported;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\ConnectionNameNotSupported;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DbalCleanupTaskHandler;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DropIndexTask;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DropTableTask;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\UnexpectedConnectionType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(DbalCleanupTaskHandler::class)]
final class DbalCleanupTaskHandlerTest extends TestCase
{
    public function testNoSupport(): void
    {
        $handler = new DbalCleanupTaskHandler(
            $this->createMock(Connection::class),
        );

        self::assertFalse($handler->supports(new stdClass()));
    }

    public function testSupports(): void
    {
        $handler = new DbalCleanupTaskHandler(
            $this->createMock(Connection::class),
        );

        self::assertTrue($handler->supports(new DropTableTask('test')));
        self::assertTrue($handler->supports(new DropIndexTask('test', 'test')));
    }

    public function testHandleNoSupportedTask(): void
    {
        $handler = new DbalCleanupTaskHandler(
            $this->createMock(Connection::class),
        );

        $this->expectException(CleanupTaskNotSupported::class);

        $handler(new stdClass());
    }

    public function testHandleDropTable(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->expects($this->once())->method('dropTable')->with('test');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('createSchemaManager')
            ->willReturn($schemaManager);

        $handler = new DbalCleanupTaskHandler($connection);

        $handler(new DropTableTask('test'));
    }

    public function testHandleDropIndex(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->expects($this->once())->method('dropIndex')->with('foo', 'bar');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('createSchemaManager')
            ->willReturn($schemaManager);

        $handler = new DbalCleanupTaskHandler($connection);

        $handler(new DropIndexTask('foo', 'bar'));
    }

    public function testHandleWithConnectionRegistry(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->expects($this->once())->method('dropTable')->with('test');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('createSchemaManager')
            ->willReturn($schemaManager);

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getConnection')
            ->with('foo')
            ->willReturn($connection);

        $handler = new DbalCleanupTaskHandler($registry);

        $handler(new DropTableTask('test', 'foo'));
    }

    public function testHandleWithConnectionRegistryAndUnexpectedType(): void
    {
        $registry = $this->createMock(ConnectionRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getConnection')
            ->with('foo')
            ->willReturn(new stdClass());

        $handler = new DbalCleanupTaskHandler($registry);

        $this->expectException(UnexpectedConnectionType::class);
        $handler(new DropTableTask('test', 'foo'));
    }

    public function testHandleWithConnectionAndConnectionNameNotSupported(): void
    {
        $connection = $this->createMock(Connection::class);
        $handler = new DbalCleanupTaskHandler($connection);

        $this->expectException(ConnectionNameNotSupported::class);
        $handler(new DropTableTask('test', 'foo'));
    }
}
