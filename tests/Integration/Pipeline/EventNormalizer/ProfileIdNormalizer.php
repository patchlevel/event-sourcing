<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Pipeline\EventNormalizer;

use Attribute;
use InvalidArgumentException;
use Patchlevel\EventSourcing\Serializer\Normalizer\Normalizer;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\ProfileId;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ProfileIdNormalizer implements Normalizer
{
    public function normalize(mixed $value): string
    {
        if (!$value instanceof ProfileId) {
            throw new InvalidArgumentException();
        }

        return $value->toString();
    }

    public function denormalize(mixed $value): ProfileId|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException();
        }

        return ProfileId::fromString($value);
    }
}
