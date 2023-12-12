<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;
use Patchlevel\EventSourcing\Schema\StoreNotSupported;
use Patchlevel\EventSourcing\Store\DoctrineStore;
use Patchlevel\EventSourcing\Store\Store;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Schema\DoctrineSchemaManager */
final class DoctrineSchemaManagerTest extends TestCase
{
    use ProphecyTrait;

    public function testCreate(): void
    {
        $store = $this->prophesize(DoctrineStore::class);
        $connection = $this->prophesize(Connection::class);
        $schema = $this->prophesize(Schema::class);
        $platform = $this->prophesize(AbstractPlatform::class);

        $connection->getDatabasePlatform()->willReturn($platform->reveal());
        $schema->toSql(Argument::type(AbstractPlatform::class))->willReturn(['this is sql!']);
        $store->schema()->willReturn($schema->reveal());

        $connection->executeStatement('this is sql!')->shouldBeCalledOnce();
        $store->connection()->willReturn($connection->reveal());

        $doctrineSchemaManager = new DoctrineSchemaManager();
        $doctrineSchemaManager->create($store->reveal());
    }

    public function testCreateNotSupportedStore(): void
    {
        $store = $this->prophesize(Store::class);

        $doctrineSchemaManager = new DoctrineSchemaManager();

        $this->expectException(StoreNotSupported::class);
        $doctrineSchemaManager->create($store->reveal());
    }

    public function testDryRunCreate(): void
    {
        $store = $this->prophesize(DoctrineStore::class);
        $connection = $this->prophesize(Connection::class);
        $schema = $this->prophesize(Schema::class);
        $platform = $this->prophesize(AbstractPlatform::class);

        $connection->getDatabasePlatform()->willReturn($platform->reveal());
        $schema->toSql(Argument::type(AbstractPlatform::class))->willReturn(['this is sql!']);
        $store->schema()->willReturn($schema->reveal());
        $store->connection()->willReturn($connection->reveal());

        $doctrineSchemaManager = new DoctrineSchemaManager();
        $sqlStatements = $doctrineSchemaManager->dryRunCreate($store->reveal());

        self::assertSame(['this is sql!'], $sqlStatements);
    }

    public function testDryRunCreateNotSupportedStore(): void
    {
        $store = $this->prophesize(Store::class);

        $doctrineSchemaManager = new DoctrineSchemaManager();

        $this->expectException(StoreNotSupported::class);
        $doctrineSchemaManager->dryRunCreate($store->reveal());
    }

    public function testUpdate(): void
    {
        $store = $this->prophesize(DoctrineStore::class);
        $connection = $this->prophesize(Connection::class);
        $fromSchema = $this->prophesize(Schema::class);
        $toSchema = $this->prophesize(Schema::class);
        $schemaManager = $this->prophesize(AbstractSchemaManager::class);
        $platform = $this->prophesize(AbstractPlatform::class);

        $table = new Table('foo');

        $toSchema->getNamespaces()->willReturn([]);
        $toSchema->getTables()->willReturn([$table]);
        $toSchema->getTable('foo')->willReturn($table);
        $toSchema->getSequences()->willReturn([]);
        $toSchema->getName()->willReturn('toSchema');

        $fromSchema->getNamespaces()->willReturn([]);
        $fromSchema->getTables()->willReturn([]);
        $fromSchema->getSequences()->willReturn([]);
        $fromSchema->hasTable('foo')->willReturn(false);

        $platform->supportsSchemas()->willReturn(false);
        $platform->supportsForeignKeyConstraints()->willReturn(false);
        $platform->supportsSequences()->willReturn(false);
        $platform->supportsForeignKeyConstraints()->willReturn(false);

        $platform->getDropTablesSQL([])->shouldBeCalledOnce()->willReturn([]);
        $platform->getCreateTablesSQL(['foo' => $table])->shouldBeCalledOnce()->willReturn(['CREATE TABLE foo;']);

        $schemaManager->createSchema()->willReturn($fromSchema->reveal());

        /** @psalm-suppress InternalMethod */
        $schemaManager->createComparator()->willReturn(new Comparator());

        $connection->createSchemaManager()->willReturn($schemaManager->reveal());
        $connection->getDatabasePlatform()->willReturn($platform->reveal());
        $store->schema()->willReturn($toSchema->reveal());

        $connection->executeStatement('CREATE TABLE foo;')->shouldBeCalled();
        $store->connection()->willReturn($connection->reveal());

        $doctrineSchemaManager = new DoctrineSchemaManager();
        $doctrineSchemaManager->update($store->reveal());
    }

    public function testUpdateNotSupportedStore(): void
    {
        $store = $this->prophesize(Store::class);

        $doctrineSchemaManager = new DoctrineSchemaManager();

        $this->expectException(StoreNotSupported::class);
        $doctrineSchemaManager->update($store->reveal());
    }

    public function testDryRunUpdate(): void
    {
        $store = $this->prophesize(DoctrineStore::class);
        $connection = $this->prophesize(Connection::class);
        $fromSchema = $this->prophesize(Schema::class);
        $toSchema = $this->prophesize(Schema::class);
        $schemaManager = $this->prophesize(AbstractSchemaManager::class);
        $platform = $this->prophesize(AbstractPlatform::class);

        $fromSchema->getNamespaces()->willReturn([]);
        $fromSchema->getTables()->willReturn([]);
        $fromSchema->getSequences()->willReturn([]);
        $toSchema->getNamespaces()->willReturn([]);
        $toSchema->getTables()->willReturn([]);
        $toSchema->getSequences()->willReturn([]);

        $platform->supportsSchemas()->willReturn(false);
        $platform->supportsForeignKeyConstraints()->willReturn(false);
        $platform->supportsSequences()->willReturn(false);
        $platform->supportsForeignKeyConstraints()->willReturn(false);

        $platform->getDropTablesSQL([])->shouldBeCalledOnce()->willReturn([]);
        $platform->getCreateTablesSQL([])->shouldBeCalledOnce()->willReturn([]);

        $schemaManager->createSchema()->willReturn($fromSchema->reveal());

        /** @psalm-suppress InternalMethod */
        $schemaManager->createComparator()->willReturn(new Comparator());

        $connection->createSchemaManager()->willReturn($schemaManager->reveal());
        $connection->getDatabasePlatform()->willReturn($platform->reveal());
        $store->schema()->willReturn($toSchema->reveal());
        $store->connection()->willReturn($connection->reveal());

        $doctrineSchemaManager = new DoctrineSchemaManager();
        $sqlStatements = $doctrineSchemaManager->dryRunUpdate($store->reveal());

        self::assertSame([], $sqlStatements);
    }

    public function testDryRunUpdateNotSupportedStore(): void
    {
        $store = $this->prophesize(Store::class);

        $doctrineSchemaManager = new DoctrineSchemaManager();

        $this->expectException(StoreNotSupported::class);
        $doctrineSchemaManager->dryRunUpdate($store->reveal());
    }

    public function testDrop(): void
    {
        $store = $this->prophesize(DoctrineStore::class);
        $connection = $this->prophesize(Connection::class);
        $currentSchema = $this->prophesize(Schema::class);
        $toSchema = $this->prophesize(Schema::class);
        $schemaManager = $this->prophesize(AbstractSchemaManager::class);

        $toSchema->getTables()->willReturn([
            new Table('foo'),
            new Table('bar'),
        ]);

        $currentSchema->hasTable('foo')->willReturn(true);
        $currentSchema->hasTable('bar')->willReturn(false);

        $schemaManager->createSchema()->willReturn($currentSchema->reveal());
        $connection->createSchemaManager()->willReturn($schemaManager->reveal());
        $store->schema()->willReturn($toSchema->reveal());

        $connection->executeStatement('DROP TABLE foo;')->shouldBeCalled();
        $connection->executeStatement('DROP TABLE bar;')->shouldNotBeCalled();
        $store->connection()->willReturn($connection->reveal());

        $doctrineSchemaManager = new DoctrineSchemaManager();
        $doctrineSchemaManager->drop($store->reveal());
    }

    public function testDropNotSupported(): void
    {
        $store = $this->prophesize(Store::class);

        $doctrineSchemaManager = new DoctrineSchemaManager();

        $this->expectException(StoreNotSupported::class);
        $doctrineSchemaManager->drop($store->reveal());
    }

    public function testdDryRunDrop(): void
    {
        $store = $this->prophesize(DoctrineStore::class);
        $connection = $this->prophesize(Connection::class);
        $currentSchema = $this->prophesize(Schema::class);
        $toSchema = $this->prophesize(Schema::class);
        $schemaManager = $this->prophesize(AbstractSchemaManager::class);

        $toSchema->getTables()->willReturn([
            new Table('foo'),
            new Table('bar'),
        ]);

        $currentSchema->hasTable('foo')->willReturn(true);
        $currentSchema->hasTable('bar')->willReturn(false);

        $schemaManager->createSchema()->willReturn($currentSchema->reveal());
        $connection->createSchemaManager()->willReturn($schemaManager->reveal());
        $store->schema()->willReturn($toSchema->reveal());

        $store->connection()->willReturn($connection->reveal());

        $doctrineSchemaManager = new DoctrineSchemaManager();
        $queries = $doctrineSchemaManager->dryRunDrop($store->reveal());
        self::assertSame(['DROP TABLE foo;'], $queries);
    }

    public function testDryRunDropNotSupported(): void
    {
        $store = $this->prophesize(Store::class);

        $doctrineSchemaManager = new DoctrineSchemaManager();

        $this->expectException(StoreNotSupported::class);
        $doctrineSchemaManager->dryRunDrop($store->reveal());
    }
}
