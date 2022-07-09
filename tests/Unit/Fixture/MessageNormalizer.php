<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Serializer\Normalizer\InvalidArgument;
use Patchlevel\EventSourcing\Serializer\Normalizer\Normalizer;

use function is_array;

final class MessageNormalizer implements Normalizer
{
    /**
     * @return array<array-key, mixed>|null
     */
    public function normalize(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof Message) {
            throw new InvalidArgument();
        }

        return $value->toArray();
    }

    public function denormalize(mixed $value): ?Message
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
