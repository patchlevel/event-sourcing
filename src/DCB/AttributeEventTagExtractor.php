<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Attribute\EventTag;
use ReflectionClass;
use RuntimeException;

use function array_keys;
use function get_debug_type;
use function hash;
use function is_int;
use function is_string;
use function sprintf;

/** @experimental */
final class AttributeEventTagExtractor implements EventTagExtractor
{
    /** @return list<string> */
    public function extract(object $event): array
    {
        $reflectionClass = new ReflectionClass($event);

        $tags = [];

        foreach ($reflectionClass->getProperties() as $property) {
            $attributes = $property->getAttributes(EventTag::class);

            if ($attributes === []) {
                continue;
            }

            /** @var EventTag $attribute */
            $attribute = $attributes[0]->newInstance();

            $value = $property->getValue($event);

            if ($value instanceof AggregateRootId) {
                $value = $value->toString();
            }

            if (!is_string($value) && !is_int($value)) {
                throw new RuntimeException(
                    sprintf('Event tag value must be a string or an int, %s given', get_debug_type($value)),
                );
            }

            $value = (string)$value;

            if ($attribute->hash) {
                $value = hash($attribute->hash, $value);
            }

            if ($attribute->prefix) {
                $value = $attribute->prefix . ':' . $value;
            }

            $tags[$value] = true;
        }

        return array_keys($tags);
    }
}
