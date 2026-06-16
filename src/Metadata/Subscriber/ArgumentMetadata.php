<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Symfony\Component\TypeInfo\Type;

final class ArgumentMetadata
{
    /** @param list<object> $attributes */
    public function __construct(
        public readonly string $name,
        public readonly Type $type,
        public readonly array $attributes = [],
    ) {
    }

    /**
     * @param class-string<T> $attributeClass
     *
     * @return T|null
     *
     * @template T of object
     */
    public function attribute(string $attributeClass): object|null
    {
        foreach ($this->attributes as $attribute) {
            if ($attribute instanceof $attributeClass) {
                return $attribute;
            }
        }

        return null;
    }
}
