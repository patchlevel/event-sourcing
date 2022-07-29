<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use InvalidArgumentException;

use function in_array;

/**
 * @final
 */
class DoctrineHelper
{
    public function databaseName(Connection $connection): string
    {
        /**
         * @psalm-suppress InternalMethod
         */
        $params = $connection->getParams();

        if (isset($params['path'])) {
            return $params['path'];
        }

        if (isset($params['dbname'])) {
            return $params['dbname'];
        }

        throw new InvalidArgumentException(
            "Connection does not contain a 'path' or 'dbname' parameter and cannot be created."
        );
    }

    /**
     * @codeCoverageIgnore
     */
    public function copyConnectionWithoutDatabase(Connection $connection): Connection
    {
        /**
         * @psalm-suppress InternalMethod
         */
        $params = $connection->getParams();

        /**
         * @psalm-suppress InvalidArrayOffset
         */
        unset($params['dbname'], $params['path'], $params['url']);

        return DriverManager::getConnection($params);
    }

    public function hasDatabase(Connection $connection, string $databaseName): bool
    {
        return in_array($databaseName, $connection->createSchemaManager()->listDatabases());
    }

    public function createDatabase(Connection $connection, string $databaseName): void
    {
        $connection->createSchemaManager()->createDatabase(
            $connection->getDatabasePlatform()->quoteSingleIdentifier($databaseName)
        );
    }

    public function dropDatabase(Connection $connection, string $databaseName): void
    {
        $connection->createSchemaManager()->dropDatabase(
            $connection->getDatabasePlatform()->quoteSingleIdentifier($databaseName)
        );
    }
}
