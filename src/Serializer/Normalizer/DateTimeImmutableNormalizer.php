<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Normalizer;

use DateTimeImmutable;

use function is_string;

final class DateTimeImmutableNormalizer implements Normalizer
{
    public function __construct(
        private readonly string $format = DateTimeImmutable::ATOM
    ) {
    }

    public function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof DateTimeImmutable) {
            throw new InvalidArgument();
        }

        return $value->format($this->format);
    }

    public function denormalize(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgument();
        }

        $date = DateTimeImmutable::createFromFormat($this->format, $value);

        if ($date === false) {
            throw new InvalidArgument();
        }

        return $date;
    }
}
