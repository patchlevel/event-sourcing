<?php declare(strict_types=1);

namespace Patchlevel\EventSourcing;

use Symfony\Component\Messenger\MessageBusInterface;
use function array_shift;

/**
 * Stellt sicher, dass die Events in der Richtigen Reihenfolge abgearbeitet werden.
 */
final class EventStream
{
    /**
     * @var array<int, object>
     */
    private array $queue;
    private MessageBusInterface $eventBus;
    private bool $process;

    public function __construct(MessageBusInterface $eventBus)
    {
        $this->queue = [];
        $this->eventBus = $eventBus;
        $this->process = false;
    }

    public function dispatch(object $event): void
    {
        $this->queue[] = $event;

        if ($this->process) {
            return;
        }

        $this->process = true;

        while ($event = array_shift($this->queue)) {
            $this->eventBus->dispatch($event);
        }

        $this->process = false;
    }
}
