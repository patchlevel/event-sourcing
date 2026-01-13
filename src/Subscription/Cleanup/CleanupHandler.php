<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Cleanup;

interface CleanupHandler
{
    public function __invoke(object $task): void;

    public function supports(object $task): bool;
}
