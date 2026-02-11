<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Cleanup;

use Patchlevel\EventSourcing\Subscription\Subscription;
use Throwable;

final class DefaultCleaner implements Cleaner
{
    /** @param iterable<CleanupHandler> $handlers */
    public function __construct(
        private readonly iterable $handlers = [],
    ) {
    }

    public function cleanup(Subscription $subscription): void
    {
        $tasks = $subscription->cleanupTasks();

        if (!$tasks) {
            return;
        }

        foreach ($tasks as $task) {
            foreach ($this->handlers as $handler) {
                if (!$handler->supports($task)) {
                    continue;
                }

                try {
                    $handler($task);
                } catch (Throwable $exception) {
                    throw new CleanupFailed(
                        $subscription->id(),
                        $task,
                        $handler::class,
                        $exception,
                    );
                }

                continue 2;
            }

            throw new NoHandlerForCleanupTask($task);
        }
    }
}
