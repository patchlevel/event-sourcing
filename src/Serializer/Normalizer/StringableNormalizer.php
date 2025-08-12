<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Normalizer;

use Attribute;
use Patchlevel\EventSourcing\Stringable;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\InvalidType;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\TypeAwareNormalizer;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ObjectType;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
final class StringableNormalizer implements Normalizer, TypeAwareNormalizer
{
    public function __construct(
        /** @var class-string<Stringable>|null */
        private string|null $stringableClass = null,
    ) {
    }

    public function normalize(mixed $value): string|null
    {
        if ($value === null) {
            return null;
        }

        $class = $this->stringableClass();

        if (!$value instanceof Stringable) {
            throw InvalidArgument::withWrongType($class, $value);
        }

        return $value->toString();
    }

    public function denormalize(mixed $value): Stringable|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw InvalidArgument::withWrongType('string', $value);
        }

        $class = $this->stringableClass();

        return $class::fromString($value);
    }

    /** @return class-string<Stringable> */
    public function stringableClass(): string
    {
        if ($this->stringableClass === null) {
            throw InvalidType::missingType();
        }

        return $this->stringableClass;
    }

    public function handleType(Type|null $type): void
    {
        if ($type === null || $this->stringableClass !== null) {
            return;
        }

        if (!$type instanceof ObjectType) {
            return;
        }

        $this->stringableClass = $type->getClassName();
    }
}
