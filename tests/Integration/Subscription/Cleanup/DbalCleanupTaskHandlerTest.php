<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Cleanup;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Index;
use Doctrine\Persistence\ConnectionRegistry;
use Patchlevel\EventSourcing\Subscription\Cleanup\CleanupTaskNotSupported;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\ConnectionNameNotSupported;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DbalCleanupTaskHandler;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DropIndexTask;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DropTableTask;
use Patchlevel\EventSourcing\Tests\DbalManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use stdClass;

use function array_map;
use function strtolower;

#[CoversNothing]
final class DbalCleanupTaskHandlerTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();
    }

    public function tearDown(): void
    {
        $this->connection->close();
    }

    public function testDropTable(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE projection (id VARCHAR(255) NOT NULL PRIMARY KEY, name VARCHAR(255) NOT NULL)',
        );
        $this->connection->executeStatement('CREATE INDEX projection_name_idx ON projection (name)');

        (new DbalCleanupTaskHandler($this->connection))(new DropTableTask('projection'));

        self::assertFalse($this->connection->createSchemaManager()->tablesExist(['projection']));
    }

    public function testDropUnknownTable(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE projection (id VARCHAR(255) NOT NULL PRIMARY KEY, name VARCHAR(255) NOT NULL)',
        );
        $this->connection->executeStatement('CREATE INDEX projection_name_idx ON projection (name)');

        (new DbalCleanupTaskHandler($this->connection))(new DropTableTask('unknown'));

        self::assertTrue($this->connection->createSchemaManager()->tablesExist(['projection']));
    }

    public function testDropIndex(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE projection (id VARCHAR(255) NOT NULL PRIMARY KEY, name VARCHAR(255) NOT NULL)',
        );
        $this->connection->executeStatement('CREATE INDEX projection_name_idx ON projection (name)');

        (new DbalCleanupTaskHandler($this->connection))(new DropIndexTask('projection_name_idx', 'projection'));

        $indexes = array_map(
            static fn (Index $index) => strtolower($index->getObjectName()->getIdentifier()->getValue()),
            $this->connection->createSchemaManager()->introspectTableIndexesByUnquotedName('projection'),
        );

        self::assertNotContains('projection_name_idx', $indexes);
        self::assertTrue($this->connection->createSchemaManager()->tablesExist(['projection']));
    }

    public function testDropIndexIgnoresTheCase(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE projection (id VARCHAR(255) NOT NULL PRIMARY KEY, name VARCHAR(255) NOT NULL)',
        );
        $this->connection->executeStatement('CREATE INDEX projection_name_idx ON projection (name)');

        (new DbalCleanupTaskHandler($this->connection))(new DropIndexTask('PROJECTION_NAME_IDX', 'projection'));

        $indexes = array_map(
            static fn (Index $index) => strtolower($index->getObjectName()->getIdentifier()->getValue()),
            $this->connection->createSchemaManager()->introspectTableIndexesByUnquotedName('projection'),
        );

        self::assertNotContains('projection_name_idx', $indexes);
    }

    public function testDropUnknownIndex(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE projection (id VARCHAR(255) NOT NULL PRIMARY KEY, name VARCHAR(255) NOT NULL)',
        );
        $this->connection->executeStatement('CREATE INDEX projection_name_idx ON projection (name)');

        (new DbalCleanupTaskHandler($this->connection))(new DropIndexTask('unknown_idx', 'projection'));

        $indexes = array_map(
            static fn (Index $index) => strtolower($index->getObjectName()->getIdentifier()->getValue()),
            $this->connection->createSchemaManager()->introspectTableIndexesByUnquotedName('projection'),
        );

        self::assertContains('projection_name_idx', $indexes);
    }

    public function testDropIndexOfUnknownTable(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE projection (id VARCHAR(255) NOT NULL PRIMARY KEY, name VARCHAR(255) NOT NULL)',
        );
        $this->connection->executeStatement('CREATE INDEX projection_name_idx ON projection (name)');

        (new DbalCleanupTaskHandler($this->connection))(new DropIndexTask('projection_name_idx', 'unknown'));

        $indexes = array_map(
            static fn (Index $index) => strtolower($index->getObjectName()->getIdentifier()->getValue()),
            $this->connection->createSchemaManager()->introspectTableIndexesByUnquotedName('projection'),
        );

        self::assertContains('projection_name_idx', $indexes);
    }

    public function testConnectionNameWithRegistry(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE projection (id VARCHAR(255) NOT NULL PRIMARY KEY, name VARCHAR(255) NOT NULL)',
        );
        $this->connection->executeStatement('CREATE INDEX projection_name_idx ON projection (name)');

        $registry = $this->createMock(ConnectionRegistry::class);
        $registry->expects($this->once())
            ->method('getConnection')
            ->with('projection_connection')
            ->willReturn($this->connection);

        (new DbalCleanupTaskHandler($registry))(new DropTableTask('projection', 'projection_connection'));

        self::assertFalse($this->connection->createSchemaManager()->tablesExist(['projection']));
    }

    public function testConnectionNameWithoutRegistry(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE projection (id VARCHAR(255) NOT NULL PRIMARY KEY, name VARCHAR(255) NOT NULL)',
        );
        $this->connection->executeStatement('CREATE INDEX projection_name_idx ON projection (name)');

        $this->expectException(ConnectionNameNotSupported::class);

        (new DbalCleanupTaskHandler($this->connection))(new DropTableTask('projection', 'other'));
    }

    public function testSupports(): void
    {
        $handler = new DbalCleanupTaskHandler($this->connection);

        self::assertTrue($handler->supports(new DropTableTask('projection')));
        self::assertTrue($handler->supports(new DropIndexTask('projection_name_idx', 'projection')));
        self::assertFalse($handler->supports(new stdClass()));
    }

    public function testUnsupportedTask(): void
    {
        $this->expectException(CleanupTaskNotSupported::class);

        (new DbalCleanupTaskHandler($this->connection))(new stdClass());
    }
}
