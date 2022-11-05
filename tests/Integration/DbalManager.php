<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use Doctrine\DBAL\DriverManager;
use Patchlevel\EventSourcing\Console\DoctrineHelper;
use RuntimeException;

use function getenv;
use function in_array;
use function is_string;
use function str_replace;

final class DbalManager
{
    public const DEFAULT_DB_NAME = 'eventstore';

    public static function createConnection(string $dbName = self::DEFAULT_DB_NAME): Connection
    {
        $dbUrl = getenv('DB_URL');

        if (!is_string($dbUrl)) {
            throw new RuntimeException('missing DB_URL env');
        }

        if ($dbName !== self::DEFAULT_DB_NAME) {
            $dbUrl = str_replace(self::DEFAULT_DB_NAME, $dbName, $dbUrl);
        }

        $connection = DriverManager::getConnection(['url' => $dbUrl]);

        if ($connection->getDriver() instanceof AbstractSQLiteDriver) {
            return $connection;
        }

        $tempConnection = (new DoctrineHelper())->copyConnectionWithoutDatabase($connection);

        $schemaManager = $tempConnection->createSchemaManager();
        $databases = $schemaManager->listDatabases();

        if (in_array($dbName, $databases, true)) {
            $schemaManager->dropDatabase($dbName);
        }

        $schemaManager->createDatabase($dbName);
        $tempConnection->close();

        return $connection;
    }
}
