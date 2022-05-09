<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Normalizer;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;

use function is_string;

class DateTimeNormalizer implements Normalizer
{
    public function __construct(
        private readonly bool $immutable = true,
        private readonly string $format = DateTimeInterface::ATOM
    ) {
    }

    public function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof DateTimeInterface) {
            throw new InvalidArgument();
        }

        return $value->format($this->format);
    }

    public function denormalize(mixed $value): ?DateTimeInterface
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgument();
        }

        if ($this->immutable) {
            $date = DateTimeImmutable::createFromFormat($this->format, $value);
        } else {
            $date = DateTime::createFromFormat($this->format, $value);
        }

        if ($date === false) {
            throw new InvalidArgument();
        }

        return $date;
    }
}
