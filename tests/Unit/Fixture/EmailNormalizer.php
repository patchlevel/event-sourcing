<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Serializer\Normalizer\Normalizer;

use function is_string;

class EmailNormalizer implements Normalizer
{
    public function normalize(mixed $value): string
    {
        if (!$value instanceof Email) {
            throw new InvalidArgumentException();
        }

        return $value->toString();
    }

    public function denormalize(mixed $value): ?Email
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException();
        }

        return Email::fromString($value);
    }
}
