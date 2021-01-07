<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

final class SymfonyEventBus implements EventBus
{
    private MessageBusInterface $bus;

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    public function dispatch(AggregateChanged $event): void
    {
        $envelope = (new Envelope($event))
            ->with(new DispatchAfterCurrentBusStamp());

        $this->bus->dispatch($envelope);
    }

    /**
     * @param list<Listener> $listeners
     * @return static
     */
    public static function create(array $listeners = []): self
    {
        $bus = new MessageBus([
            new HandleMessageMiddleware(
                new HandlersLocator([
                    AggregateChanged::class => $listeners,
                ]),
                true
            ),
        ]);

        return new static($bus);
    }
}
