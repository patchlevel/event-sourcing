<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Symfony\Component\Messenger\MessageBusInterface;

final class SymfonyBusBridge implements Listener
{
    private MessageBusInterface $bus;

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    public function __invoke(AggregateChanged $event): void
    {
        $this->bus->dispatch($event);
    }
}
