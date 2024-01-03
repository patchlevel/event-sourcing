<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use ReflectionClass;

use function array_key_exists;

abstract class Subscriber implements Listener
{
    /** @var array<class-string, string>|null */
    private array|null $subscribeMethods = null;

    final public function __invoke(Message $message): void
    {
        if ($this->subscribeMethods === null) {
            $this->init();
        }

        $method = $this->subscribeMethods[$message->event()::class] ?? null;

        if (!$method) {
            return;
        }

        $this->$method($message);
    }

    private function init(): void
    {
        $reflection = new ReflectionClass(static::class);
        $methods = $reflection->getMethods();

        $this->subscribeMethods = [];

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Subscribe::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $eventClass = $instance->eventClass;

                if (array_key_exists($eventClass, $this->subscribeMethods)) {
                    throw new DuplicateSubscribeMethod(
                        static::class,
                        $eventClass,
                        $this->subscribeMethods[$eventClass],
                        $method->getName(),
                    );
                }

                $this->subscribeMethods[$eventClass] = $method->getName();
            }
        }
    }
}
