<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Cleanup;

use RuntimeException;
use Throwable;

use function sprintf;

final class CleanupFailed extends RuntimeException
{
    /** @param class-string $handlerClass */
    public function __construct(
        string $subscriptionId,
        public readonly object $task,
        string $handlerClass,
        Throwable $exception,
    ) {
        parent::__construct(
            sprintf(
                'Cleanup of subscription "%s" failed for task "%s" in handler "%s"',
                $subscriptionId,
                $task::class,
                $handlerClass,
            ),
            0,
            $exception,
        );
    }
}
