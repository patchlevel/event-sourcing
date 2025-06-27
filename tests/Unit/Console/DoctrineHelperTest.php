<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use InvalidArgumentException;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineHelper::class)]
final class DoctrineHelperTest extends TestCase
{
    public function testDatabaseNameWithPath(): void
    {
        $helper = new DoctrineHelper();

        $connection = $this->createMock(Connection::class);
        $connection->method('getParams')->willReturn(['path' => 'test']);

        self::assertSame('test', $helper->databaseName($connection));
    }

    public function testDatabaseNameWithDatabaseName(): void
    {
        $helper = new DoctrineHelper();

        $connection = $this->createMock(Connection::class);
        $connection->method('getParams')->willReturn(['dbname' => 'test']);

        self::assertSame('test', $helper->databaseName($connection));
    }

    public function testDatabaseNameThrowException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $helper = new DoctrineHelper();

        $connection = $this->createMock(Connection::class);
        $connection->method('getParams')->willReturn([]);

        $helper->databaseName($connection);
    }

    public function testHasDatabase(): void
    {
        $helper = new DoctrineHelper();

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('listDatabases')->willReturn(['test']);

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        self::assertSame(true, $helper->hasDatabase($connection, 'test'));
    }

    public function testCreateDatabase(): void
    {
        $helper = new DoctrineHelper();

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->expects($this->atLeastOnce())->method('createDatabase')->with('`test`');

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $helper->createDatabase($connection, 'test');
    }

    public function testDropDatabase(): void
    {
        $helper = new DoctrineHelper();

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->expects($this->atLeastOnce())->method('dropDatabase')->with('`test`');

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('createSchemaManager')->willReturn($schemaManager);

        $helper->dropDatabase($connection, 'test');
    }
}
