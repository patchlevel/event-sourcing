<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;

use function array_values;
use function sprintf;

final class DoctrineSchemaDirector implements DryRunSchemaDirector, DoctrineSchemaProvider
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SchemaConfigurator $schemaConfigurator,
    ) {
    }

    public function create(): void
    {
        $queries = $this->dryRunCreate();

        foreach ($queries as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    /**
     * @return list<string>
     */
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

    /**
     * @return list<string>
     */
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

    /**
     * @return list<string>
     */
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

        $this->schemaConfigurator->configureSchema($schema, $this->connection);

        return $schema;
    }
}
