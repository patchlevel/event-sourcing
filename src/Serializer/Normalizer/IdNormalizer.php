<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Normalizer;

use Attribute;
use Patchlevel\EventSourcing\Identifier\Identifier;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\InvalidType;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\TypeAwareNormalizer;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
final class IdNormalizer implements Normalizer, TypeAwareNormalizer
{
    public function __construct(
        /** @var class-string<Identifier>|null */
        private string|null $identifierClass = null,
    ) {
    }

    public function normalize(mixed $value): string|null
    {
        if ($value === null) {
            return null;
        }

        $class = $this->identifierClass();

        if (!$value instanceof Identifier) {
            throw InvalidArgument::withWrongType($class, $value);
        }

        return $value->toString();
    }

    public function denormalize(mixed $value): Identifier|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw InvalidArgument::withWrongType('string', $value);
        }

        $class = $this->identifierClass();

        return $class::fromString($value);
    }

    /** @return class-string<Identifier> */
    public function identifierClass(): string
    {
        if ($this->identifierClass === null) {
            throw InvalidType::missingType();
        }

        return $this->identifierClass;
    }

    public function handleType(Type|null $type): void
    {
        if ($this->identifierClass !== null || $type === null) {
            return;
        }

        if ($type instanceof NullableType) {
            $type = $type->getWrappedType();
        }

        if (!$type instanceof ObjectType) {
            return;
        }

        $this->identifierClass = $type->getClassName();
    }
}
