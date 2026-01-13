<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Cleanup;

use RuntimeException;

use function sprintf;

final class NoHandlerForCleanupTask extends RuntimeException
{
    public function __construct(
        public readonly object $task,
    ) {
        parent::__construct(sprintf('No cleanup handler for task %s', $task::class));
    }
}
