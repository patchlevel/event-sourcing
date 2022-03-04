<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Attribute\Handle;
use ReflectionClass;

use function array_key_exists;

abstract class BaseProjection implements Projection
{
    /** @var  array<class-string<self>, array<class-string<AggregateChanged>, string>> */
    private static array $handleMethods = [];

    /**
     * @return array<class-string<AggregateChanged>, string>
     */
    public function handledEvents(): array
    {
        if (array_key_exists(static::class, self::$handleMethods)) {
            return self::$handleMethods[static::class];
        }

        $reflector = new ReflectionClass(static::class);
        $methods = $reflector->getMethods();

        self::$handleMethods[static::class] = [];

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Handle::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $eventClass = $instance->aggregateChangedClass();

                if (array_key_exists($eventClass, self::$handleMethods[static::class])) {
                    throw new DuplicateHandleMethod(
                        self::class,
                        $eventClass,
                        self::$handleMethods[static::class][$eventClass],
                        $method->getName()
                    );
                }

                self::$handleMethods[static::class][$eventClass] = $method->getName();
            }
        }

        return self::$handleMethods[static::class];
    }

    public function create(): void
    {
        // do nothing
    }

    public function drop(): void
    {
        // do nothing
    }
}
