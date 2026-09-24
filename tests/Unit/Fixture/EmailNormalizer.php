<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Attribute;
use InvalidArgumentException;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\Normalizer;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class EmailNormalizer implements Normalizer
{
    /** @param array<string, mixed> $context */
    public function normalize(mixed $value, array $context): string
    {
        if (!$value instanceof Email) {
            throw new InvalidArgumentException();
        }

        return $value->toString();
    }

    /** @param array<string, mixed> $context */
    public function denormalize(mixed $value, array $context): Email|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgument();
        }

        return Email::fromString($value);
    }
}
