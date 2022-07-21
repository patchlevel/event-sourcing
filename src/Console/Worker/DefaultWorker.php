<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Worker;

use Closure;
use Patchlevel\EventSourcing\Console\Worker\Event\WorkerRunningEvent;
use Patchlevel\EventSourcing\Console\Worker\Event\WorkerStartedEvent;
use Patchlevel\EventSourcing\Console\Worker\Event\WorkerStoppedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function max;
use function microtime;
use function usleep;

final class DefaultWorker implements Worker
{
    private bool $shouldStop = false;

    public function __construct(
        private readonly Closure $job,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function run(int $sleepTimer = 1000): void
    {
        $this->logger?->debug('Worker starting');

        $this->eventDispatcher->dispatch(new WorkerStartedEvent($this));

        while (!$this->shouldStop) {
            $this->logger?->debug('Worker starting job run');

            $startTime = microtime(true);

            ($this->job)();

            $ranTime = (int)(microtime(true) - $startTime);

            $this->logger?->debug('Worker finished job run ({ranTime}ms)', ['ranTime' => $ranTime]);

            $this->eventDispatcher->dispatch(new WorkerRunningEvent($this));

            if ($this->shouldStop) {
                break;
            }

            $sleepFor = max($sleepTimer - $ranTime, 0);

            if ($sleepFor <= 0) {
                continue;
            }

            $this->logger?->debug('Worker sleep for {sleepTimer}ms', ['sleepTimer' => $sleepFor]);
            usleep($sleepFor);
        }

        $this->logger?->debug('Worker stopped');

        $this->eventDispatcher->dispatch(new WorkerStoppedEvent($this));

        $this->logger?->debug('Worker terminated');
    }

    public function stop(): void
    {
        $this->logger?->debug('Worker received stop signal');
        $this->shouldStop = true;
    }
}
