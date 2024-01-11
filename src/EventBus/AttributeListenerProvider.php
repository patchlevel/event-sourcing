<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use ReflectionClass;

final class AttributeListenerProvider implements ListenerProvider
{
    /** @var array<string, list<ListenerDescriptor>>|null */
    private array|null $subscribeMethods = null;

    /** @param iterable<object> $listeners */
    public function __construct(
        private readonly iterable $listeners,
    ) {
    }

    /** @return iterable<ListenerDescriptor> */
    public function listenersForEvent(object $event): iterable
    {
        if ($this->subscribeMethods !== null) {
            return $this->subscribeMethods[$event::class] ?? [];
        }

        $this->subscribeMethods = [];

        foreach ($this->listeners as $listener) {
            $reflection = new ReflectionClass($listener);
            $methods = $reflection->getMethods();

            foreach ($methods as $method) {
                $attributes = $method->getAttributes(Subscribe::class);

                foreach ($attributes as $attribute) {
                    $instance = $attribute->newInstance();
                    $eventClass = $instance->eventClass;

                    $this->subscribeMethods[$eventClass][] = new ListenerDescriptor(
                        $listener->{$method->getName()}(...),
                    );
                }
            }
        }

        return $this->subscribeMethods[$event::class] ?? [];
    }
}
