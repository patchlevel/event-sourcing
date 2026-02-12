<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Cleanup;

interface CleanupTaskHandler
{
    public function __invoke(object $task): void;

    public function supports(object $task): bool;
}
