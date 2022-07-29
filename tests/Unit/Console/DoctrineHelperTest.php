<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use InvalidArgumentException;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Console\DoctrineHelper */
final class DoctrineHelperTest extends TestCase
{
    use ProphecyTrait;

    public function testDatabaseNameWithPath(): void
    {
        $helper = new DoctrineHelper();

        $connection = $this->prophesize(Connection::class);
        $connection->getParams()->willReturn(['path' => 'test']);

        self::assertSame('test', $helper->databaseName($connection->reveal()));
    }

    public function testDatabaseNameWithDatabaseName(): void
    {
        $helper = new DoctrineHelper();

        $connection = $this->prophesize(Connection::class);
        $connection->getParams()->willReturn(['dbname' => 'test']);

        self::assertSame('test', $helper->databaseName($connection->reveal()));
    }

    public function testDatabaseNameThrowException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $helper = new DoctrineHelper();

        $connection = $this->prophesize(Connection::class);
        $connection->getParams()->willReturn([]);

        $helper->databaseName($connection->reveal());
    }

    public function testHasDatabase(): void
    {
        $helper = new DoctrineHelper();

        $schemaManager = $this->prophesize(AbstractSchemaManager::class);
        $schemaManager->listDatabases()->willReturn(['test']);

        $connection = $this->prophesize(Connection::class);
        $connection->createSchemaManager()->willReturn($schemaManager);

        self::assertSame(true, $helper->hasDatabase($connection->reveal(), 'test'));
    }

    public function testCreateDatabase(): void
    {
        $helper = new DoctrineHelper();

        $schemaManager = $this->prophesize(AbstractSchemaManager::class);
        $schemaManager->createDatabase('`test`')->shouldBeCalled();

        $connection = $this->prophesize(Connection::class);
        $connection->getDatabasePlatform()->willReturn(new MySQLPlatform());
        $connection->createSchemaManager()->willReturn($schemaManager);

        $helper->createDatabase($connection->reveal(), 'test');
    }

    public function testDropDatabase(): void
    {
        $helper = new DoctrineHelper();

        $schemaManager = $this->prophesize(AbstractSchemaManager::class);
        $schemaManager->dropDatabase('`test`')->shouldBeCalled();

        $connection = $this->prophesize(Connection::class);
        $connection->getDatabasePlatform()->willReturn(new MySQLPlatform());
        $connection->createSchemaManager()->willReturn($schemaManager);

        $helper->dropDatabase($connection->reveal(), 'test');
    }
}
