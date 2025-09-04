<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

use Patchlevel\EventSourcing\Attribute\EventTag;
use Patchlevel\EventSourcing\Identifier\Identifier;
use ReflectionClass;
use Stringable;

use function array_keys;
use function hash;
use function is_int;
use function is_string;

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

            if ($value instanceof Stringable || is_int($value)) {
                $value = (string)$value;
            }

            if ($value instanceof Identifier) {
                $value = $value->toString();
            }

            if (!is_string($value)) {
                throw EventTagExtractorError::invalidValueType(
                    $event::class,
                    $property->getName(),
                    $value,
                );
            }

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
