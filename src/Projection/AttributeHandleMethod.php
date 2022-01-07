<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Attribute\Handle;
use ReflectionClass;

use function array_key_exists;

/**
 * @psalm-require-implements Projection
 */
trait AttributeHandleMethod
{
    /** @var array<class-string<AggregateChanged>, string>|null */
    private static ?array $handleMethods = null;

    /**
     * @return array<class-string<AggregateChanged>, string>
     */
    public function handledEvents(): array
    {
        if (self::$handleMethods !== null) {
            return self::$handleMethods;
        }

        $reflector = new ReflectionClass(self::class);
        $methods = $reflector->getMethods();

        self::$handleMethods = [];

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Handle::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $eventClass = $instance->aggregateChangedClass();

                if (array_key_exists($eventClass, self::$handleMethods)) {
                    throw new DuplicateHandleMethod(
                        self::class,
                        $eventClass,
                        self::$handleMethods[$eventClass],
                        $method->getName()
                    );
                }

                self::$handleMethods[$eventClass] = $method->getName();
            }
        }

        return self::$handleMethods;
    }
}
