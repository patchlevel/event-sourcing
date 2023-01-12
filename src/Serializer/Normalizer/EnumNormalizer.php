<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Normalizer;

use Attribute;
use BackedEnum;
use ValueError;

use function is_int;
use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class EnumNormalizer implements Normalizer
{
    public function __construct(
        /** @var class-string<BackedEnum> */
        private readonly string $enum
    ) {
    }

    public function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof BackedEnum) {
            throw new InvalidArgument();
        }

        return $value->value;
    }

    public function denormalize(mixed $value): ?BackedEnum
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgument();
        }

        $enumClass = $this->enum;

        try {
            return $enumClass::from($value);
        } catch (ValueError) {
            throw new InvalidArgument();
        }
    }
}
