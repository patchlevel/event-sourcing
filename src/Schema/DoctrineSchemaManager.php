<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Schema;

use Doctrine\DBAL\Schema\Comparator;
use Patchlevel\EventSourcing\Store\DoctrineStore;
use Patchlevel\EventSourcing\Store\Store;

use function array_values;
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

    /**
     * @return list<string>
     */
    public function dryRunCreate(Store $store): array
    {
        if (!$store instanceof DoctrineStore) {
            throw new StoreNotSupported();
        }

        $connection = $store->connection();
        $schema = $store->schema();

        return array_values($schema->toSql($connection->getDatabasePlatform()));
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

    /**
     * @return list<string>
     */
    public function dryRunUpdate(Store $store): array
    {
        if (!$store instanceof DoctrineStore) {
            throw new StoreNotSupported();
        }

        $connection = $store->connection();

        $fromSchema = $connection->createSchemaManager()->createSchema();
        $toSchema = $store->schema();

        $comparator = new Comparator();
        $diff = $comparator->compare($fromSchema, $toSchema);

        return array_values($diff->toSql($connection->getDatabasePlatform()));
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

    /**
     * @return list<string>
     */
    public function dryRunDrop(Store $store): array
    {
        if (!$store instanceof DoctrineStore) {
            throw new StoreNotSupported();
        }

        $connection = $store->connection();
        $currentSchema = $connection->createSchemaManager()->createSchema();
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
