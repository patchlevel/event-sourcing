<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Cleanup;

use RuntimeException;

use function sprintf;

final class CleanupTaskNotSupported extends RuntimeException
{
    /** @param class-string<CleanupTaskHandler> $handler */
    public function __construct(
        object $task,
        string $handler,
    ) {
        parent::__construct(
            sprintf('Task "%s" is not supported by handler "%s"', $task::class, $handler),
        );
    }
}
