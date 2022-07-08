<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Worker;

interface Worker
{
    /**
     * @param 0|positive-int $sleepTimer sleepTimer in microseconds
     */
    public function run(int $sleepTimer = 1000): void;

    public function stop(): void;
}
