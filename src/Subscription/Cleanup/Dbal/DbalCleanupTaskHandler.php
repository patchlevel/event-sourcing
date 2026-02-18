<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Cleanup\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ConnectionRegistry;
use Patchlevel\EventSourcing\Subscription\Cleanup\CleanupTaskHandler;
use Patchlevel\EventSourcing\Subscription\Cleanup\CleanupTaskNotSupported;

final class DbalCleanupTaskHandler implements CleanupTaskHandler
{
    public function __construct(
        private readonly Connection|ConnectionRegistry $connection,
    ) {
    }

    public function __invoke(object $task): void
    {
        if ($task instanceof DropTableTask) {
            $this->connection($task->connectionName)->createSchemaManager()->dropTable($task->table);

            return;
        }

        if ($task instanceof DropIndexTask) {
            $this->connection($task->connectionName)->createSchemaManager()->dropIndex($task->index, $task->table);

            return;
        }

        throw new CleanupTaskNotSupported($task, self::class);
    }

    public function supports(object $task): bool
    {
        return $task instanceof DropTableTask || $task instanceof DropIndexTask;
    }

    private function connection(string|null $connectionName): Connection
    {
        if ($this->connection instanceof ConnectionRegistry) {
            $connection = $this->connection->getConnection($connectionName);

            if (!$connection instanceof Connection) {
                throw new UnexpectedConnectionType($connectionName, Connection::class, $connection::class);
            }

            return $connection;
        }

        if ($connectionName === null) {
            return $this->connection;
        }

        throw new ConnectionNameNotSupported($connectionName);
    }
}
