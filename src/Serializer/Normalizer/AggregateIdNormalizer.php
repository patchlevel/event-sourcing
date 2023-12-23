<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Normalizer;

use Attribute;
use Cspray\Phinal\AllowInheritance;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\Normalizer;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY)]
#[AllowInheritance('you can make specific normalizers for different classes')]
class AggregateIdNormalizer implements Normalizer
{
    public function __construct(
        /** @var class-string<AggregateRootId> */
        private readonly string $aggregateIdClass,
    ) {
    }

    public function normalize(mixed $value): string|null
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof AggregateRootId) {
            throw InvalidArgument::withWrongType($this->aggregateIdClass, $value);
        }

        return $value->toString();
    }

    public function denormalize(mixed $value): AggregateRootId|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw InvalidArgument::withWrongType('string', $value);
        }

        $class = $this->aggregateIdClass;

        return $class::fromString($value);
    }
}
