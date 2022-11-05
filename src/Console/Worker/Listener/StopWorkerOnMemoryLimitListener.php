<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Worker\Listener;

use Patchlevel\EventSourcing\Console\Worker\Bytes;
use Patchlevel\EventSourcing\Console\Worker\Event\WorkerRunningEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function memory_get_usage;

final class StopWorkerOnMemoryLimitListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly Bytes $memoryLimit,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        $usedMemory = $this->usedMemory();

        if ($usedMemory->value() <= $this->memoryLimit->value()) {
            return;
        }

        $this->logger?->info(
            'Worker stopped due to memory limit of {limit} bytes exceeded ({memory} bytes used)',
            ['limit' => $this->memoryLimit->value(), 'memory' => $usedMemory->value()]
        );

        $event->worker->stop();
    }

    private function usedMemory(): Bytes
    {
        return new Bytes(memory_get_usage(true));
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [WorkerRunningEvent::class => 'onWorkerRunning'];
    }
}
