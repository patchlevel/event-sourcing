<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Console\Worker\Listener;

use Patchlevel\EventSourcing\Console\Worker\Event\WorkerStartedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function function_exists;
use function pcntl_signal;

use const SIGTERM;

final class StopWorkerOnSigtermSignalListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        pcntl_signal(SIGTERM, function () use ($event): void {
            $this->logger?->info('Received SIGTERM signal.');
            $event->worker->stop();
        });
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        if (!function_exists('pcntl_signal')) {
            return [];
        }

        return [WorkerStartedEvent::class => 'onWorkerStarted'];
    }
}
