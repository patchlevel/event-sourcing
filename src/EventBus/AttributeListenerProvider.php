<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use ReflectionClass;

use function array_merge;

final class AttributeListenerProvider implements ListenerProvider
{
    /** @var array<string, list<ListenerDescriptor>>|null */
    private array|null $subscribeMethods = null;

    /** @param iterable<object> $listeners */
    public function __construct(
        private readonly iterable $listeners,
    ) {
    }

    /**
     * @param class-string $eventClass
     *
     * @return iterable<ListenerDescriptor>
     */
    public function listenersForEvent(string $eventClass): iterable
    {
        if ($this->subscribeMethods !== null) {
            return array_merge(
                $this->subscribeMethods[$eventClass] ?? [],
                $this->subscribeMethods[Subscribe::ALL] ?? [],
            );
        }

        $this->subscribeMethods = [];

        foreach ($this->listeners as $listener) {
            $reflection = new ReflectionClass($listener);
            $methods = $reflection->getMethods();

            foreach ($methods as $method) {
                $attributes = $method->getAttributes(Subscribe::class);

                foreach ($attributes as $attribute) {
                    $instance = $attribute->newInstance();
                    $subscribedEventClass = $instance->eventClass;

                    $this->subscribeMethods[$subscribedEventClass][] = new ListenerDescriptor(
                        $listener->{$method->getName()}(...),
                    );
                }
            }
        }

        return array_merge(
            $this->subscribeMethods[$eventClass] ?? [],
            $this->subscribeMethods[Subscribe::ALL] ?? [],
        );
    }
}
