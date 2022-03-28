<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

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

    public function dispatch(Message ...$messages): void
    {
        foreach ($messages as $message) {
            $envelope = (new Envelope($message))
                ->with(new DispatchAfterCurrentBusStamp());

            $this->bus->dispatch($envelope);
        }
    }

    /**
     * @param list<Listener> $listeners
     */
    public static function create(array $listeners = []): static
    {
        $bus = new MessageBus([
            new HandleMessageMiddleware(
                new HandlersLocator([Message::class => $listeners]),
                true
            ),
        ]);

        return new static($bus);
    }
}
