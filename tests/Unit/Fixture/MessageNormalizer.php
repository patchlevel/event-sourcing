<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Attribute;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\Normalizer;

use function is_array;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class MessageNormalizer implements Normalizer
{
    /**
     * @param array<string, mixed> $context
     *
     * @return array<array-key, mixed>|null
     */
    public function normalize(mixed $value, array $context): array|null
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof Message) {
            throw new InvalidArgument();
        }

        return $value->toArray();
    }

    /** @param array<string, mixed> $context */
    public function denormalize(mixed $value, array $context): Message|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw new InvalidArgument();
        }

        return Message::fromArray($value);
    }
}
