<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Schema;

use Doctrine\DBAL\Schema\Comparator;
use Patchlevel\EventSourcing\Store\DoctrineStore;
use Patchlevel\EventSourcing\Store\Store;

use function sprintf;

class DoctrineSchemaManager implements DryRunSchemaManager
{
    public function create(Store $store): void
    {
        if (!$store instanceof DoctrineStore) {
            throw new StoreNotSupported();
        }

        $connection = $store->connection();
        $queries = $this->dryRunCreate($store);

        foreach ($queries as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public function dryRunCreate(Store $store): array
    {
        if (!$store instanceof DoctrineStore) {
            throw new StoreNotSupported();
        }

        $connection = $store->connection();
        $schema = $store->schema();

        return $schema->toSql($connection->getDatabasePlatform());
    }

    public function update(Store $store): void
    {
        if (!$store instanceof DoctrineStore) {
            throw new StoreNotSupported();
        }

        $connection = $store->connection();
        $queries = $this->dryRunUpdate($store);

        foreach ($queries as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public function dryRunUpdate(Store $store): array
    {
        if (!$store instanceof DoctrineStore) {
            throw new StoreNotSupported();
        }

        $connection = $store->connection();

        $fromSchema = $connection->getSchemaManager()->createSchema();
        $toSchema = $store->schema();

        $comparator = new Comparator();
        $diff = $comparator->compare($fromSchema, $toSchema);

        return $diff->toSql($connection->getDatabasePlatform());
    }

    public function drop(Store $store): void
    {
        if (!$store instanceof DoctrineStore) {
            throw new StoreNotSupported();
        }

        $connection = $store->connection();
        $queries = $this->dryRunDrop($store);

        foreach ($queries as $sql) {
            $connection->executeStatement($sql);
        }
    }

    public function dryRunDrop(Store $store): array
    {
        if (!$store instanceof DoctrineStore) {
            throw new StoreNotSupported();
        }

        $connection = $store->connection();
        $currentSchema = $connection->getSchemaManager()->createSchema();
        $schema = $store->schema();

        $queries = [];

        foreach ($schema->getTableNames() as $tableName) {
            if (!$currentSchema->hasTable($tableName)) {
                continue;
            }

            $queries[] = sprintf('DROP TABLE %s;', $tableName);
        }

        return $queries;
    }
}
