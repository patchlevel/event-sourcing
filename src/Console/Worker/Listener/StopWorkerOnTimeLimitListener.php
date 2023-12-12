<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Worker\Listener;

use Patchlevel\EventSourcing\Console\Worker\Event\WorkerRunningEvent;
use Patchlevel\EventSourcing\Console\Worker\Event\WorkerStartedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function microtime;

final class StopWorkerOnTimeLimitListener implements EventSubscriberInterface
{
    private float $endTime = 0;

    /** @param positive-int $timeLimitInSeconds */
    public function __construct(
        private readonly int $timeLimitInSeconds,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function onWorkerStarted(): void
    {
        $this->endTime = microtime(true) + $this->timeLimitInSeconds;
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if ($this->endTime >= microtime(true)) {
            return;
        }

        $event->worker->stop();
        $this->logger?->info(
            'Worker stopped due to time limit of {timeLimit}s exceeded',
            ['timeLimit' => $this->timeLimitInSeconds],
        );
    }

    /** @return array<class-string, string> */
    public static function getSubscribedEvents(): array
    {
        return [
            WorkerStartedEvent::class => 'onWorkerStarted',
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }
}
