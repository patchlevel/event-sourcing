<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Store\Normalizer;

use Attribute;
use InvalidArgumentException;
use Patchlevel\EventSourcing\Tests\Integration\Store\ProfileId;
use Patchlevel\Hydrator\Normalizer\Normalizer;

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
