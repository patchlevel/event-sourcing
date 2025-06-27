<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\SchemaDiff;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineSchemaDirector::class)]
final class DoctrineSchemaDirectorTest extends TestCase
{
    public function testCreate(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('createSchemaConfig')->willReturn(new SchemaConfig());

        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('getCreateTablesSQL')->willReturn(['this is sql!']);
        $platform->method('supportsSchemas')->willReturn(false);

        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->expects($this->once())->method('executeStatement')->with('this is sql!');

        $schemaConfigurator = $this->createMock(DoctrineSchemaConfigurator::class);
        $schemaConfigurator->expects($this->once())->method('configureSchema')->with($this->isInstanceOf(Schema::class), $connection);

        $doctrineSchemaManager = new DoctrineSchemaDirector($connection, $schemaConfigurator);
        $doctrineSchemaManager->create();
    }

    public function testDryRunCreate(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager
            ->expects($this->once())
            ->method('createSchemaConfig')
            ->willReturn(new SchemaConfig());

        $platform = $this->createMock(AbstractPlatform::class);
        $platform
            ->expects($this->once())
            ->method('getCreateTablesSQL')
            ->willReturn(['this is sql!']);
        $platform
            ->expects($this->once())
            ->method('supportsSchemas')
            ->willReturn(false);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('createSchemaManager')
            ->willReturn($schemaManager);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $schemaConfigurator = $this->createMock(DoctrineSchemaConfigurator::class);
        $schemaConfigurator->expects($this->once())->method('configureSchema')->with($this->isInstanceOf(Schema::class), $connection);

        $doctrineSchemaManager = new DoctrineSchemaDirector($connection, $schemaConfigurator);
        $sqlStatements = $doctrineSchemaManager->dryRunCreate();

        self::assertSame(['this is sql!'], $sqlStatements);
    }

    public function testUpdate(): void
    {
        $diff = $this->createMock(SchemaDiff::class);

        $fromSchema = $this->createMock(Schema::class);

        $comperator = $this->createMock(Comparator::class);
        $comperator
            ->expects($this->once())
            ->method('compareSchemas')
            ->with($fromSchema, $this->isInstanceOf(Schema::class))
            ->willReturn($diff);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager
            ->expects($this->once())
            ->method('createComparator')
            ->willReturn($comperator);
        $schemaManager
            ->expects($this->once())
            ->method('introspectSchema')
            ->willReturn($fromSchema);
        $schemaManager
            ->expects($this->once())
            ->method('createSchemaConfig')
            ->willReturn(new SchemaConfig());

        $platform = $this->createMock(AbstractPlatform::class);
        $platform
            ->expects($this->once())
            ->method('getAlterSchemaSQL')
            ->with($diff)
            ->willReturn(['x', 'y']);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('createSchemaManager')
            ->willReturn($schemaManager);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);
        $connection
            ->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnMap([
                ['x', 1],
                ['y', 1],
            ]);

        $schemaConfigurator = $this->createMock(DoctrineSchemaConfigurator::class);
        $schemaConfigurator->expects($this->once())->method('configureSchema')->with($this->isInstanceOf(Schema::class), $connection);

        $doctrineSchemaManager = new DoctrineSchemaDirector($connection, $schemaConfigurator);
        $doctrineSchemaManager->update();
    }

    public function testDryRunUpdate(): void
    {
        $diff = $this->createMock(SchemaDiff::class);

        $fromSchema = $this->createMock(Schema::class);

        $comperator = $this->createMock(Comparator::class);
        $comperator
            ->expects($this->once())
            ->method('compareSchemas')
            ->with($fromSchema, $this->isInstanceOf(Schema::class))
            ->willReturn($diff);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager
            ->expects($this->once())
            ->method('createComparator')
            ->willReturn($comperator);
        $schemaManager
            ->expects($this->once())
            ->method('introspectSchema')
            ->willReturn($fromSchema);
        $schemaManager
            ->expects($this->once())
            ->method('createSchemaConfig')
            ->willReturn(new SchemaConfig());

        $platform = $this->createMock(AbstractPlatform::class);
        $platform
            ->expects($this->once())
            ->method('getAlterSchemaSQL')
            ->with($diff)->willReturn(['x', 'y']);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('createSchemaManager')
            ->willReturn($schemaManager);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $schemaConfigurator = $this->createMock(DoctrineSchemaConfigurator::class);
        $schemaConfigurator->expects($this->once())->method('configureSchema')->with($this->isInstanceOf(Schema::class), $connection);

        $doctrineSchemaManager = new DoctrineSchemaDirector($connection, $schemaConfigurator);
        $sqlStatements = $doctrineSchemaManager->dryRunUpdate();

        self::assertSame(['x', 'y'], $sqlStatements);
    }

    public function testDrop(): void
    {
        $connection = $this->createMock(Connection::class);
        $currentSchema = $this->createMock(Schema::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->expects($this->once())->method('createSchemaConfig')->willReturn(new SchemaConfig());

        $currentSchema
            ->expects($this->exactly(2))
            ->method('hasTable')
            ->willReturnMap([
                ['foo', true],
                ['bar', false],
            ]);

        $schemaManager->expects($this->once())->method('introspectSchema')->willReturn($currentSchema);
        $connection->expects($this->exactly(2))->method('createSchemaManager')->willReturn($schemaManager);

        $connection->expects($this->once())->method('executeStatement')->with('DROP TABLE foo;');

        $schemaConfigurator = $this->createMock(DoctrineSchemaConfigurator::class);
        $schemaConfigurator
            ->expects($this->once())
            ->method('configureSchema')
            ->willReturnCallback(static function (Schema $schema) {
                $schema->createTable('foo');
                $schema->createTable('bar');

                return true;
            });

        $doctrineSchemaManager = new DoctrineSchemaDirector($connection, $schemaConfigurator);
        $doctrineSchemaManager->drop();
    }

    public function testDryRunDrop(): void
    {
        $connection = $this->createMock(Connection::class);
        $currentSchema = $this->createMock(Schema::class);
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->expects($this->once())->method('createSchemaConfig')->willReturn(new SchemaConfig());

        $currentSchema
            ->expects($this->exactly(2))
            ->method('hasTable')
            ->willReturnMap([
                ['foo', true],
                ['bar', false],
            ]);

        $schemaManager->expects($this->once())->method('introspectSchema')->willReturn($currentSchema);
        $connection->expects($this->exactly(2))->method('createSchemaManager')->willReturn($schemaManager);

        $schemaConfigurator = $this->createMock(DoctrineSchemaConfigurator::class);
        $schemaConfigurator
            ->expects($this->once())
            ->method('configureSchema')
            ->willReturnCallback(static function (Schema $schema) {
                $schema->createTable('foo');
                $schema->createTable('bar');

                return true;
            });

        $doctrineSchemaManager = new DoctrineSchemaDirector($connection, $schemaConfigurator);
        $queries = $doctrineSchemaManager->dryRunDrop();

        self::assertSame(['DROP TABLE foo;'], $queries);
    }
}
