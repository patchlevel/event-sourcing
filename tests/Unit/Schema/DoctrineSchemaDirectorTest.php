<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Schema\SchemaConfigurator;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector */
final class DoctrineSchemaDirectorTest extends TestCase
{
    use ProphecyTrait;

    public function testCreate(): void
    {
        $schemaManager = $this->prophesize(AbstractSchemaManager::class);
        $schemaManager->createSchemaConfig()->willReturn();

        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->getCreateTablesSQL(Argument::any(), Argument::any())->willReturn(['this is sql!']);
        $platform->supportsSchemas()->willReturn(false);

        $connection = $this->prophesize(Connection::class);
        $connection->createSchemaManager()->willReturn($schemaManager->reveal());
        $connection->getDatabasePlatform()->willReturn($platform->reveal());
        $connection->executeStatement('this is sql!')->shouldBeCalledOnce();

        $schemaConfigurator = $this->prophesize(SchemaConfigurator::class);
        $schemaConfigurator->configureSchema(Argument::type(Schema::class), $connection->reveal())->shouldBeCalledOnce();

        $doctrineSchemaManager = new DoctrineSchemaDirector(
            $connection->reveal(),
            $schemaConfigurator->reveal()
        );

        $doctrineSchemaManager->create();
    }

    public function testDryRunCreate(): void
    {
        $schemaManager = $this->prophesize(AbstractSchemaManager::class);
        $schemaManager->createSchemaConfig()->willReturn();

        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->getCreateTablesSQL(Argument::any(), Argument::any())->willReturn(['this is sql!']);
        $platform->supportsSchemas()->willReturn(false);

        $connection = $this->prophesize(Connection::class);
        $connection->createSchemaManager()->willReturn($schemaManager->reveal());
        $connection->getDatabasePlatform()->willReturn($platform->reveal());

        $schemaConfigurator = $this->prophesize(SchemaConfigurator::class);
        $schemaConfigurator->configureSchema(Argument::type(Schema::class), $connection->reveal())->shouldBeCalledOnce();

        $doctrineSchemaManager = new DoctrineSchemaDirector(
            $connection->reveal(),
            $schemaConfigurator->reveal()
        );

        $sqlStatements = $doctrineSchemaManager->dryRunCreate();

        self::assertSame(['this is sql!'], $sqlStatements);
    }

    public function testUpdate(): void
    {
        $fromSchema = $this->prophesize(Schema::class);
        $fromSchema->getNamespaces()->willReturn([]);
        $fromSchema->getTables()->willReturn([]);
        $fromSchema->getSequences()->willReturn([]);

        $schemaManager = $this->prophesize(AbstractSchemaManager::class);
        $schemaManager->createSchemaConfig()->willReturn();
        $schemaManager->createComparator()->willReturn(new Comparator());
        $schemaManager->createSchema()->willReturn($fromSchema->reveal());

        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->getCreateTablesSQL(Argument::any(), Argument::any())->willReturn(['this is sql!']);
        $platform->supportsSchemas()->willReturn(false);
        $platform->supportsForeignKeyConstraints()->willReturn(false);
        $platform->supportsSequences()->willReturn(false);
        $platform->supportsForeignKeyConstraints()->willReturn(false);

        $platform->getDropTablesSQL([])->shouldBeCalledOnce()->willReturn([]);
        $platform->getCreateTablesSQL([])->shouldBeCalledOnce()->willReturn([]);

        $connection = $this->prophesize(Connection::class);
        $connection->createSchemaManager()->willReturn($schemaManager->reveal());
        $connection->getDatabasePlatform()->willReturn($platform->reveal());

        $schemaConfigurator = $this->prophesize(SchemaConfigurator::class);
        $schemaConfigurator->configureSchema(Argument::type(Schema::class), $connection->reveal())->shouldBeCalledOnce();

        $doctrineSchemaManager = new DoctrineSchemaDirector(
            $connection->reveal(),
            $schemaConfigurator->reveal()
        );

        $doctrineSchemaManager->update();
    }

    public function testDryRunUpdate(): void
    {
        $fromSchema = $this->prophesize(Schema::class);
        $fromSchema->getNamespaces()->willReturn([]);
        $fromSchema->getTables()->willReturn([]);
        $fromSchema->getSequences()->willReturn([]);

        $schemaManager = $this->prophesize(AbstractSchemaManager::class);
        $schemaManager->createSchemaConfig()->willReturn();
        $schemaManager->createComparator()->willReturn(new Comparator());
        $schemaManager->createSchema()->willReturn($fromSchema->reveal());

        $platform = $this->prophesize(AbstractPlatform::class);
        $platform->getCreateTablesSQL(Argument::any(), Argument::any())->willReturn(['this is sql!']);
        $platform->supportsSchemas()->willReturn(false);
        $platform->supportsForeignKeyConstraints()->willReturn(false);
        $platform->supportsSequences()->willReturn(false);
        $platform->supportsForeignKeyConstraints()->willReturn(false);

        $platform->getDropTablesSQL([])->shouldBeCalledOnce()->willReturn([]);
        $platform->getCreateTablesSQL([])->shouldBeCalledOnce()->willReturn([]);

        $connection = $this->prophesize(Connection::class);
        $connection->createSchemaManager()->willReturn($schemaManager->reveal());
        $connection->getDatabasePlatform()->willReturn($platform->reveal());

        $schemaConfigurator = $this->prophesize(SchemaConfigurator::class);
        $schemaConfigurator->configureSchema(Argument::type(Schema::class), $connection->reveal())->shouldBeCalledOnce();

        $doctrineSchemaManager = new DoctrineSchemaDirector(
            $connection->reveal(),
            $schemaConfigurator->reveal()
        );

        $sqlStatements = $doctrineSchemaManager->dryRunUpdate();

        self::assertSame([], $sqlStatements);
    }

    public function testDrop(): void
    {
        $connection = $this->prophesize(Connection::class);
        $currentSchema = $this->prophesize(Schema::class);
        $schemaManager = $this->prophesize(AbstractSchemaManager::class);
        $schemaManager->createSchemaConfig()->willReturn();

        $currentSchema->hasTable('foo')->willReturn(true);
        $currentSchema->hasTable('bar')->willReturn(false);

        $schemaManager->createSchema()->willReturn($currentSchema->reveal());
        $connection->createSchemaManager()->willReturn($schemaManager->reveal());

        $connection->executeStatement('DROP TABLE foo;')->shouldBeCalled();
        $connection->executeStatement('DROP TABLE bar;')->shouldNotBeCalled();

        $schemaConfigurator = $this->prophesize(SchemaConfigurator::class);
        $schemaConfigurator->configureSchema(Argument::that(static function (Schema $schema) {
            $schema->createTable('foo');
            $schema->createTable('bar');

            return true;
        }), $connection->reveal())->shouldBeCalledOnce();

        $doctrineSchemaManager = new DoctrineSchemaDirector(
            $connection->reveal(),
            $schemaConfigurator->reveal()
        );

        $doctrineSchemaManager->drop();
    }

    public function testDryRunDrop(): void
    {
        $connection = $this->prophesize(Connection::class);
        $currentSchema = $this->prophesize(Schema::class);
        $schemaManager = $this->prophesize(AbstractSchemaManager::class);
        $schemaManager->createSchemaConfig()->willReturn();

        $currentSchema->hasTable('foo')->willReturn(true);
        $currentSchema->hasTable('bar')->willReturn(false);

        $schemaManager->createSchema()->willReturn($currentSchema->reveal());
        $connection->createSchemaManager()->willReturn($schemaManager->reveal());

        $schemaConfigurator = $this->prophesize(SchemaConfigurator::class);
        $schemaConfigurator->configureSchema(Argument::that(static function (Schema $schema) {
            $schema->createTable('foo');
            $schema->createTable('bar');

            return true;
        }), $connection->reveal())->shouldBeCalledOnce();

        $doctrineSchemaManager = new DoctrineSchemaDirector(
            $connection->reveal(),
            $schemaConfigurator->reveal()
        );

        $queries = $doctrineSchemaManager->dryRunDrop();

        self::assertSame(['DROP TABLE foo;'], $queries);
    }
}
