<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Worker\Listener;

use Patchlevel\EventSourcing\Console\Worker\Event\WorkerRunningEvent;
use Patchlevel\EventSourcing\Console\Worker\Event\WorkerStartedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class StopWorkerOnIterationLimitListener implements EventSubscriberInterface
{
    private int $iteration = 0;

    /** @param positive-int $maximumNumberOfIteration */
    public function __construct(
        private readonly int $maximumNumberOfIteration,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function onWorkerStarted(): void
    {
        $this->iteration = 0;
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        $this->iteration++;

        if ($this->iteration < $this->maximumNumberOfIteration) {
            return;
        }

        $event->worker->stop();

        $this->logger?->info(
            'Worker stopped due to maximum iteration of {count}',
            ['count' => $this->maximumNumberOfIteration],
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
