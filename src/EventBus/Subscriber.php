<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Patchlevel\EventSourcing\Attribute\Handle;
use ReflectionClass;

use function array_key_exists;

abstract class Subscriber implements Listener
{
    /** @var array<class-string, string>|null */
    private array|null $handleMethods = null;

    final public function __invoke(Message $message): void
    {
        if ($this->handleMethods === null) {
            $this->init();
        }

        $method = $this->handleMethods[$message->event()::class] ?? null;

        if (!$method) {
            return;
        }

        $this->$method($message);
    }

    private function init(): void
    {
        $reflection = new ReflectionClass(static::class);
        $methods = $reflection->getMethods();

        $this->handleMethods = [];

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Handle::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $eventClass = $instance->eventClass();

                if (array_key_exists($eventClass, $this->handleMethods)) {
                    throw new DuplicateHandleMethod(
                        static::class,
                        $eventClass,
                        $this->handleMethods[$eventClass],
                        $method->getName(),
                    );
                }

                $this->handleMethods[$eventClass] = $method->getName();
            }
        }
    }
}
