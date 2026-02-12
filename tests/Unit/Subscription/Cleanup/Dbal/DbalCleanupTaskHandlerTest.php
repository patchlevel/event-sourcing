<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Cleanup\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Patchlevel\EventSourcing\Subscription\Cleanup\CleanupTaskNotSupported;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DbalCleanupTaskHandler;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DropIndexTask;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DropTableTask;
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
}
