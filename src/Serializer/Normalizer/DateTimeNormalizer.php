<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Normalizer;

use DateTime;

use function is_string;

class DateTimeNormalizer implements Normalizer
{
    public function __construct(
        private readonly string $format = DateTime::ATOM
    ) {
    }

    public function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof DateTime) {
            throw new InvalidArgument();
        }

        return $value->format($this->format);
    }

    public function denormalize(mixed $value): ?DateTime
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgument();
        }

        $date = DateTime::createFromFormat($this->format, $value);

        if ($date === false) {
            throw new InvalidArgument();
        }

        return $date;
    }
}
