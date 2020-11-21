<?php declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventStream;

use Symfony\Component\Messenger\MessageBusInterface;
use function array_shift;

final class SymfonyMessengerEventStream implements EventStream
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
