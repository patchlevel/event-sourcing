<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Cleanup\Dbal;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Subscription\Cleanup\CleanupTaskHandler;
use Patchlevel\EventSourcing\Subscription\Cleanup\CleanupTaskNotSupported;

final class DbalCleanupTaskHandler implements CleanupTaskHandler
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(object $task): void
    {
        if ($task instanceof DropTableTask) {
            $this->connection->createSchemaManager()->dropTable($task->table);

            return;
        }

        if ($task instanceof DropIndexTask) {
            $this->connection->createSchemaManager()->dropIndex($task->index, $task->table);

            return;
        }

        throw new CleanupTaskNotSupported($task, self::class);
    }

    public function supports(object $task): bool
    {
        return $task instanceof DropTableTask || $task instanceof DropIndexTask;
    }
}
