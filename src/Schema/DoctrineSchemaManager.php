<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Schema;

use Doctrine\DBAL\Schema\Comparator;
use Patchlevel\EventSourcing\Store\DoctrineStore;
use Patchlevel\EventSourcing\Store\Store;

use function sprintf;

class DoctrineSchemaManager implements SchemaManager
{
    public function create(Store $store): void
    {
        if (!$store instanceof DoctrineStore) {
            throw new StoreNotSupported();
        }

        $connection = $store->connection();
        $schema = $store->schema();

        $queries = $schema->toSql($connection->getDatabasePlatform());

        foreach ($queries as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public function update(Store $store): void
    {
        if (!$store instanceof DoctrineStore) {
            throw new StoreNotSupported();
        }

        $connection = $store->connection();

        $fromSchema = $connection->getSchemaManager()->createSchema();
        $toSchema = $store->schema();

        $comparator = new Comparator();
        $diff = $comparator->compare($fromSchema, $toSchema);

        $queries = $diff->toSql($connection->getDatabasePlatform());

        foreach ($queries as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public function drop(Store $store): void
    {
        if (!$store instanceof DoctrineStore) {
            throw new StoreNotSupported();
        }

        $connection = $store->connection();
        $currentSchema = $connection->getSchemaManager()->createSchema();
        $schema = $store->schema();

        foreach ($schema->getTableNames() as $tableName) {
            if (!$currentSchema->hasTable($tableName)) {
                continue;
            }

            $connection->executeQuery(sprintf('DROP TABLE %s;', $tableName));
        }
    }
}
