<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

interface ListenableStore
{
    public function wait(int $timeoutMilliseconds): void;
}
