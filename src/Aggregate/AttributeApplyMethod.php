<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\StrictApply;
use function method_exists;

/**
 * @psalm-require-extends AggregateRoot
 */
trait AttributeApplyMethod
{
    private static bool $strictApply = false;

    /**
     * @var array<class-string<AggregateChanged>, string>|null
     */
    private static ?array $map = null;

    protected function apply(AggregateChanged $event): void
    {
        $map = self::aggregateChangeMethodMap();

        if (!array_key_exists($event::class, $map)) {
            if (self::$strictApply) {
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

    private static function aggregateChangeMethodMap(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }

        $reflector = new \ReflectionClass(self::class);
        $attributes = $reflector->getAttributes(StrictApply::class);

        if (count($attributes) > 0) {
            self::$strictApply = true;
        }

        $methods = $reflector->getMethods();

        $map = [];

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Apply::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();

                $map[$instance->aggregateChangedClass()] = $method->getName();
            }
        }

        return self::$map = $map;
    }
}
