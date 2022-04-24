<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Serializer\Hydrator\Normalizer;

use function array_map;
use function is_array;

class MessageListNormalizer implements Normalizer
{
    public function normalize(mixed $value): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException();
        }

        return array_map(static fn (Message $message) => $message->toArray(), $value);
    }

    public function denormalize(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException();
        }

        return array_map(static fn (array $data) => Message::fromArray($data), $value);
    }
}
