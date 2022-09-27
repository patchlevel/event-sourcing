<?php

namespace Patchlevel\EventSourcing\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;

final class DoctrineSchemaDirector implements DryRunSchemaDirector
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SchemaConfigurator $schemaConfigurator,
    ) {
    }

    public function create(): void
    {
        $connection = $this->connection;
        $queries = $this->dryRunCreate();

        foreach ($queries as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public function dryRunCreate(): array
    {
        return $this->schema()->toSql($this->connection->getDatabasePlatform());
    }

    public function update(): void
    {
        $queries = $this->dryRunUpdate();

        foreach ($queries as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    public function dryRunUpdate(): array
    {
        $schemaManager = $this->connection->createSchemaManager();

        $fromSchema = $schemaManager->createSchema();
        $toSchema = $this->schema();

        $comparator = $schemaManager->createComparator();
        $diff = $comparator->compareSchemas($fromSchema, $toSchema);

        return array_values($diff->toSql($this->connection->getDatabasePlatform()));
    }

    public function drop(): void
    {
        $queries = $this->dryRunDrop();

        foreach ($queries as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    public function dryRunDrop(): array
    {
        $currentSchema = $this->connection->createSchemaManager()->createSchema();
        $schema = $this->schema();

        $queries = [];

        foreach ($schema->getTables() as $table) {
            if (!$currentSchema->hasTable($table->getName())) {
                continue;
            }

            $queries[] = sprintf('DROP TABLE %s;', $table->getName());
        }

        return $queries;
    }

    public function schema(): Schema
    {
        $schema = new Schema([], [], $this->connection->createSchemaManager()->createSchemaConfig());

        $this->schemaConfigurator->configureSchema($schema);

        return $schema;
    }
}