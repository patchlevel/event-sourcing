<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Suppress;
use ReflectionClass;

use function array_key_exists;
use function method_exists;

/**
 * @psalm-require-extends AggregateRoot
 */
trait AttributeApplyMethod
{
    /** @var array<class-string<AggregateChanged>, true> */
    private static array $suppressEvents = [];
    private static bool $suppressAll = false;

    /** @var array<class-string<AggregateChanged>, string>|null */
    private static ?array $aggregateChangeMethodMap = null;

    protected function apply(AggregateChanged $event): void
    {
        $map = self::aggregateChangeMethodMap();

        if (!array_key_exists($event::class, $map)) {
            if (!self::$suppressAll && !array_key_exists($event::class, self::$suppressEvents)) {
                throw new ApplyAttributeNotFound($this, $event);
            }

            return;
        }

        $method = $map[$event::class];

        if (!method_exists($this, $method)) {
            return;
        }

        $this->$method($event);
    }

    /**
     * @return array<class-string<AggregateChanged>, string>
     */
    private static function aggregateChangeMethodMap(): array
    {
        if (self::$aggregateChangeMethodMap !== null) {
            return self::$aggregateChangeMethodMap;
        }

        $reflector = new ReflectionClass(self::class);
        $attributes = $reflector->getAttributes(Suppress::class);

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance->suppressAll()) {
                self::$suppressAll = true;

                continue;
            }

            foreach ($instance->suppressEvents() as $event) {
                self::$suppressEvents[$event] = true;
            }
        }

        $methods = $reflector->getMethods();

        self::$aggregateChangeMethodMap = [];

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Apply::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $eventClass = $instance->aggregateChangedClass();

                if (array_key_exists($eventClass, self::$aggregateChangeMethodMap)) {
                    throw new DuplicateApplyMethod(
                        self::class,
                        $eventClass,
                        self::$aggregateChangeMethodMap[$eventClass],
                        $method->getName()
                    );
                }

                self::$aggregateChangeMethodMap[$eventClass] = $method->getName();
            }
        }

        return self::$aggregateChangeMethodMap;
    }
}
