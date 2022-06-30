<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Normalizer;

use function array_map;
use function is_array;

final class ArrayNormalizer implements Normalizer
{
    public function __construct(private readonly Normalizer $normalizer)
    {
    }

    public function normalize(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw new InvalidArgument();
        }

        return array_map(fn (mixed $value): mixed => $this->normalizer->normalize($value), $value);
    }

    public function denormalize(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw new InvalidArgument();
        }

        return array_map(fn (mixed $value): mixed => $this->normalizer->denormalize($value), $value);
    }
}
