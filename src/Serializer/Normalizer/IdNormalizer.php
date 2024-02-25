<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Normalizer;

use Attribute;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\Hydrator\Normalizer\InvalidArgument;
use Patchlevel\Hydrator\Normalizer\InvalidType;
use Patchlevel\Hydrator\Normalizer\Normalizer;
use Patchlevel\Hydrator\Normalizer\ReflectionTypeAwareNormalizer;
use Patchlevel\Hydrator\Normalizer\ReflectionTypeUtil;
use ReflectionType;

use function is_string;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class IdNormalizer implements Normalizer, ReflectionTypeAwareNormalizer
{
    public function __construct(
        /** @var class-string<AggregateRootId>|null */
        private string|null $aggregateIdClass = null,
    ) {
    }

    public function normalize(mixed $value): string|null
    {
        if ($value === null) {
            return null;
        }

        $class = $this->aggregateIdClass();

        if (!$value instanceof AggregateRootId) {
            throw InvalidArgument::withWrongType($class, $value);
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

        $class = $this->aggregateIdClass();

        return $class::fromString($value);
    }

    public function handleReflectionType(ReflectionType|null $reflectionType): void
    {
        if ($this->aggregateIdClass !== null || $reflectionType === null) {
            return;
        }

        $this->aggregateIdClass = ReflectionTypeUtil::classStringInstanceOf(
            $reflectionType,
            AggregateRootId::class,
        );
    }

    /** @return class-string<AggregateRootId> */
    public function aggregateIdClass(): string
    {
        if ($this->aggregateIdClass === null) {
            throw InvalidType::missingType();
        }

        return $this->aggregateIdClass;
    }
}
